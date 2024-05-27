<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC\MMLmappings;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Matrix;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Math\WikiTexVC\MMLmappings\BaseParsing
 */
class BaseParsingTest extends TestCase {

	public function testAccent() {
		$node = new Fun1(
			'\\widetilde',
				( new Literal( 'a' ) )
			);
		$result = BaseParsing::accent( $node, [], null, 'widetilde', '007E' );
		$this->assertStringContainsString( '~', $result );
		$this->assertStringContainsString( 'mover', $result );
	}

	public function testAccentArgPassing() {
		$node = new Fun1(
			'\\widetilde',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::accent( $node, [ 'k' => 'v' ], null, 'widetilde', '007E' );
		$this->assertStringContainsString( '<mi k="v"', $result );
	}

	public function testArray() {
		$node = new Matrix( 'matrix',
			new TexArray( new TexArray( new Literal( 'a' ) ) ) );

		$result = BaseParsing::array( $node, [], null, 'array', '007E' );
		$this->assertStringContainsString( '<mi>a</mi>', $result );
	}

	public function testBoldSymbol() {
		$node = new Fun1(
			'\\boldsymbol',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::boldsymbol( $node, [], null, 'boldsymbol' );
		$this->assertStringContainsString( 'mathvariant="bold-italic"', $result );
	}

	public function testCancel() {
		$node = new Fun1(
			'\\cancel',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::cancel( $node, [], null, 'cancel', 'something' );
		$this->assertStringContainsString( '<menclose notation="something"><mi>a</mi></menclose>',
			$result );
	}

	public function testUnderOver() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [], null, 'oXXX', '00AF' );
		$this->assertStringStartsWith( '<mrow', $result );
		$this->assertStringContainsString( 'mover', $result );
	}

	public function testUnderOverUnder() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [], null, 'uXXX', '00AF' );
		$this->assertStringContainsString( 'munder', $result );
	}

	public function testUnderOverDqUnder() {
		$node = new DQ(
			( new Literal( 'a' ) ),
			( new Literal( 'b' ) )
		);
		$result = BaseParsing::underover( $node, [], null, 'uXXX', '00AF' );
		$this->assertStringContainsString( 'munder', $result );
		$this->assertStringContainsString( 'mrow', $result );
	}

	public function testUnderArgPassing() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node, [ 'k' => 'v' ], null, 'oXXX', '00AF' );
		$this->assertStringContainsString( '<mi k="v"', $result );
	}

	public function testUnderBadArgPassing() {
		$node = new Fun1(
			'\\overline',
			( new Literal( 'a' ) )
		);
		$result = BaseParsing::underover( $node,
			[ 'k' => '"<script>alert("problem")</script>"' ], null, 'oXXX', '00AF' );
		$this->assertStringContainsString( 'k="&quot;&lt;script&gt;alert(&quot;problem&quot;)', $result );
	}
}
