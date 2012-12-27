<?php

require(__DIR__."/../CitationBuilder.php");

use \CitationBuilder\CitationBuilder;

class CitationBuilderTest extends PHPUnit_Framework_TestCase {

	public function testExceptions() {
		$invalid_tempaltes = array(
			"{@title}{, by @author} }",
			"{@title}{, {by @author }",
			"{ {@title {, by @author}"
		);

		foreach($invalid_tempaltes as $tpl) {
			try {
				$c = new CitationBuilder($tpl, array());
			} catch (InvalidArgumentException $e) {
				continue;
			}
			$this->fail('no exception thrown on invalid templates');
		}
	}

	public function testParsingCorrectness() {
		$cases = array(
			array('{@token1}{, @token2}', array(
				'token1'=>'value1',
				'token2'=>'value2'
			), 'value1, value2'),
			array('{@token1}{, @token2}', array(
				'token1'=>'value1',
				'token2'=>''
			), 'value1'),
			array('{@token1}{, @token1}', array(
				'token1'=>'value1',
			), 'value1, value1'),
			array('{@token1}{, @token2}', array(
				'token1'=>'@value1',
				'token2'=>'{value2}'
			), '@value1, {value2}'),
			array('{@token1}{, @token2 literal {@token3} literal}', array(
				'token1'=>'value1',
				'token2'=>'value2',
				'token3'=>'value3'
			),'value1, value2 literal value3 literal'),
			array('{@token1}{, @token2 literal {@token3} literal}', array(
				'token1'=>'value1',
				'token2'=>'value2',
				'token3'=>''
			),'value1, value2 literal  literal'),
			array('{@token1}{, @token2 literal {@token3} literal}', array(
				'token1'=>'value1',
				'token2'=>'',
				'token3'=>'value3'
			),'value1'),
			array('{@token1}{, @token2 literal {@token3} literal}', array(
				'token1'=>'@value1',
				'token2'=>'{value2} @ttt',
				'token3'=>'@value3'
			),'@value1, {value2} @ttt literal @value3 literal')
		);

		foreach($cases as $case) {
			$cb = new CitationBuilder($case[0], $case[1]);
			$citation = $cb->build();
      $this->assertEquals($case[2], $citation, "Template $case[0] failed");
		}
	}

}
