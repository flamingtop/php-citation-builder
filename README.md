# Build Citation Text from a spec template

    so tedious string concatenation can be avoided

## How to use

    require("CitationBuilder.php");
    use \CitationBuilder\CitationBuilder;

    $spec = {@title}{, by @author}{, @co_author}{, published by @publisher{, @publication_year}};
    
    $data = array(
      'title' => 'A Brief History of Time'
      'author' => 'Stephen Hawking',
      'co_author' => NULL,
      'publisher' => 'Bantam',
      'publication_year' => '1998'
    );
    
    $cb = new CitationBuilder($spec, $data);
    
    $citation = $cb->build();
    
    Output:
    
    "A Brief History of Time, by Stephen Hawking, published by Bantam, 1998"

## Concepts and Syntax

- Spec

    {@title}{, by @author}{, @co\_author}{, published by @publisher{, @publication\_year}}

- Segements

    {@title}
    {, by @author}
    {, @co\_author}
    {, published by @publisher{, @publication\_year}}
    {, @publication_year}

- Tokens

    @title
    @author
    @co_author
    @publisher
    @publication_year

- Keys

    title 
    author
    co_author
    publisher
    @publication_year

- Data

     array(
          'title' => 'A Brief History of Time'
          'author' => 'Stephen Hawking',
          'co_author' => NULL,
          'publisher' => 'Bantam',
          'publication_year' => '1998'
      );
    

- Relationships

    *  Managed Segments

        KEY title MANAGES {@title} SEGMENT, if title doesn't have a textual value in DATA, then the whole SEGEMENT is omitted by the builder

    * Nested Segments

        SEGMENT {, published by @publisher{, @publication_year}} is NESTED
        
            In which KEY publisher manages the outer SEGMENT and KEY publication_year manages the inner SEGMENT

