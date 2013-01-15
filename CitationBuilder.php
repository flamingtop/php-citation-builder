<?php

namespace CitationBuilder;

/**
 * Build Citation Text
 *
 * @author Shawn Xu <shallway.xu@gmail.com>
 * 
 * How it works
 
 * It takes a citation specification e.g.
 *
 *   {@title}{, by @author}{, @co_author}{, published by @publisher{, @publication_year}}
 *
 * and a mapping array e.g.
 *
 *   array(
 *     'title' => 'A Brief History of Time'
 *     'author' => 'Stephen Hawking',
 *     'co_author' => NULL,
 *     'publisher' => 'Bantam',
 *     'publication_year' => '1998'
 *   )
 *
 * then produces
 *
 *   A Brief History of Time, by Stephen Hawking, published by Bantam, 1998
 */
class CitationBuilder {
  
  /**
   * @var array
   */
  private $data = NULL;
  
  /**
   * @Var string
   */
  private $spec = NULL;
  
  
  /**
   * Instantiate a CitationBuilder Object
   * 
   * @param string $spec
   * @param array $data
   *
   */
  public function __construct($spec, $data=array()) {
    // deal with multi-line specs
    $spec = str_replace(array("\r", "\n"), "", $spec);
    
    if (!$this->_validateSpec($spec))
      throw new \InvalidArgumentException('invalid spec syntax');
    if (!is_array($data))
      throw new \InvalidArgumentException("invalid data mapping");

    $this->spec =   $spec;          
    $this->data =  $data;
  }

  
  /**
   * Build the citation text
   * 
   * @return string $citation
   */
  public function build() {
    // Parse in multiple phases untile no more token is left unexpanded
    $citation = $this->spec;
    do {
      self::debug('Parsing:'.$citation);        
      $citation = $this->_parse($citation);
      self::debug('Parsed:'.$citation);
    }
    while (preg_match('/(?<!\\\)@/', $citation));
    $citation = $this->_unescape($citation);
    return $citation;
  }
  
  
  /**
   * Make sure the curly brackets are balanced
   *
   * NOTE: Not a very strict validation: }{}{ still passes
   * @param string template
   */
  private function _validateSpec($template) {
    preg_match_all('/(?<!\\\){/', $template, $lmatches);
    preg_match_all('/(?<!\\\)}/', $template, $rmatches);
    preg_match_all('/(?<!\\\)@[\d\w_]+/', $template, $token_matches);
    return (count($lmatches[0]) == count($rmatches[0])) // { & } characters MUST be balanced
      && (count($lmatches[0]) == count($token_matches[0])); // @ character count MUST match {} pair count
  }
  
  /**
   * Recursively parse the spec and fill in the data field
   * 
   * @param string $tpl The full|partial spec to be parsed
   * @return string The full|partial spec populated with data filed(s)
   * 
   */  
  private function _parse($tpl) {
    // Figure out segments positions
    $LSTACK = array();
    $MARKS = array();
    for($i=0; $i<strlen($tpl); $i++) {
      $current_char = $tpl[$i];
      $prev_char    = $i>0 ? $tpl[$i-1] : null;
      switch($current_char) {
      case '{':
        if($prev_char != '\\') array_push($LSTACK, $i);
        break;
      case '}':
        if($prev_char != '\\') {
          if(count($LSTACK)>1)
            array_pop($LSTACK);
          else 
            $MARKS[] = array(array_pop($LSTACK), $i);
        }
        break;
      default:
        continue;
      }
    }

    // Extract the segments e.g.
    $SEGMENTS = array();
    foreach($MARKS as $m) {
      $SEGMENTS[] = substr($tpl, $m[0], $m[1]-$m[0]+1);
    }

    // solve each segments(recursively)
    $solved = array();
    foreach($SEGMENTS as $segment) {
      // skip segments without a enclosed token
      if($this->_isLiteral($segment)) continue;
      if($this->_isNested($segment)) {
        // nested
        $solved[$segment] = '{'.$this->_parse(substr($segment, 1, -1)).'}';
      } else {
        // non nested
        $solved[$segment] = $this->_expand(substr($segment, 1, -1));
      }
    }
    
    foreach($solved as $k=>$v) {
      $tpl = str_replace($k, $v, $tpl);
    }
    
    return $tpl;
  }

  
  /**
   * Determine if a segement is nested
   *
   * @param string $segment
   *   e.g. {, by @author} is unnested
   *        {, published by @publisher{, @publication_year}} is nested
   * @return boolean
   */
  private function _isNested($segment) {
    return preg_match('/(?<!\\\){/', $segment, $matches, NULL, 1);
  }
  

  /**
   * Determine if a segment has been solved
   *   e.g. {, by @author} is NOT solved
   *        , by Stephen Hawking is solved
   *
   * @params string $segement
   * @return boolean
   */
  private function _isLiteral($segment) {
    return strpos($segment, '@') === FALSE;
  }

  
  /**
   * Replace token with data
   *
   * @param string @segement
   */
  private function _expand($segment) {
    self::debug('segment:'.$segment);

    preg_match('/(?<!\\\)@(?P<key>[\w_\d+]+).*/', $segment, $match);
    $key   = $match['key'];
    self::debug('key'.$key);
    $token = '@'.$key;
    $value = $this->_escape($this->_map($key));

    /* Combo Tokens
     *
     *  E.g.
     *
     *  Given A=John, B=Bob, C=Alice, {@A+B+C} results in: John, Bob, Alice
     *  Given A=NULL, B=Bob, C=Alice, {@A+B+C} results in: Bob, Alice
     *  Given A=NULL, B=NULL, C=Alice, {@A+B+C} results in: Alice
     */
    $combo = explode('+', $key);
    if(count($combo) > 1) {
        // combo tokens like {@A|B|C}
        $tmp = array();
        foreach ($combo as $c) {
            if($this->_map($c)) {
                $tmp[] = $this->_map($c);
            }
        }
        $value = implode(', ', $tmp);
    } else {
        // ordinary tokens
        $value = $this->_map($key);
    }

    if($value)
      // found mapping value
      return str_replace($token, $value, $segment);

    if(self::$debugMode)
      // put token back to the input in debug mode
      return str_replace($token, "[$key]", $segment);

    // no mapping value, remove this segment from the spec
    return '';
  }
  

  /*
   * Map @key to its value
   *
   * @param string $key
   */
  private function _map($key) {
    return isset($this->data[$key]) ? $this->data[$key] : false;
  }
  

  ///////////////////////////////////////

  private $_escape_from = array('{', '}', '@');
  private $_escape_to   = array('\\{', '\\}', '\\@');

  /**
   * Escaping
   * @param string @string
   */
  private function _escape($string) {
    return str_replace($this->_escape_from, $this->_escape_to, $string);
  }

  /**
   * Unescaping
   * @param string @string   
   */
  private function _unescape($string) {
    return str_replace($this->_escape_to, $this->_escape_from, $string);
  }


  ///////////////////////////////////////
  
  
  /**
   * @var boolean debug mode 
   */
  private static $debugMode = FALSE;
  public static function debug($msg) {
    if(!self::$debugMode) {
      return;
    }
    error_log(__CLASS__.':'.$msg);
  }
  public static function setDebug($stat) {
    self::$debugMode = (bool)($stat);
  }

}
