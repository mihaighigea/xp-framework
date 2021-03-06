<?php
/* This class is part of the XP framework
 *
 * $Id$ 
 */

  uses(
    'unittest.TestCase',
    'lang.types.String',
    'lang.types.Character'
  );

  /**
   * TestCase
   *
   * @see      xp://lang.types.Character
   * @purpose  Unittest
   */
  class CharacterTest extends TestCase {

    /**
     * Setup this test. Forces input and output encoding to ISO-8859-1
     *
     */
    public function setUp() {
      iconv_set_encoding('input_encoding', 'iso-8859-1');
      iconv_set_encoding('output_encoding', 'iso-8859-1');
    }

    /**
     * Test a string with an incomplete multibyte character in it
     *
     */
    #[@test, @expect('lang.FormatException')]
    public function incompleteMultiByteCharacter() {
      new Character('�', 'utf-8');
    }

    /**
     * Test a string with an incomplete multibyte character in it
     *
     */
    #[@test]
    public function nullByte() {
      $this->assertEquals(new Bytes("\x00"), create(new Character(0))->getBytes());
    }

    /**
     * Test a string with an incomplete multibyte character in it
     *
     */
    #[@test]
    public function euroSymbol() {
      $this->assertEquals(new Bytes("\xe2\x82\xac"), create(new Character(8364))->getBytes('utf-8')); // &#8364; in HTML
    }
  
    /**
     * Test a string with an illegal character in it
     *
     */
    #[@test, @expect('lang.FormatException')]
    public function illegalCharacter() {
      new Character('�', 'US-ASCII');
    }

    /**
     * Test constructor invocation with three characters
     *
     */
    #[@test, @expect('lang.IllegalArgumentException')]
    public function illegalLength() {
      new Character('ABC');
    }

    /**
     * Test
     *
     */
    #[@test]
    public function usAsciiCharacter() {
      $this->assertEquals(new Bytes('H'), create(new Character('H'))->getBytes());
    }

    /**
     * Test a string containing German umlauts
     *
     */
    #[@test]
    public function umlautCharacter() {
      $this->assertEquals(new Bytes("\303\244"), create(new Character('�'))->getBytes('utf-8'));
    }

    /**
     * Test a string with utf-8 in it
     *
     */
    #[@test]
    public function utf8Character() {
      $this->assertEquals(
        new Character('ä', 'utf-8'),
        new Character('�', 'iso-8859-1')
      );
    }

    /**
     * Test transliteration
     *
     */
    #[@test, @ignore('Does not work with all iconv implementations')]
    public function transliteration() {
      $this->assertEquals('c', create(new String('č', 'utf-8'))->toString());
    }

    /**
     * Test string conversion overloading
     *
     */
    #[@test]
    public function worksWithEchoStatement() {
      ob_start();
      echo new Character('�');
      $this->assertEquals('�', ob_get_clean());
    }

    /**
     * Test string conversion overloading
     *
     */
    #[@test]
    public function stringCast() {
      $this->assertEquals('w', (string)new Character('w'));
    }

    /**
     * Test string conversion overloading
     *
     */
    #[@test]
    public function usedInStringFunction() {
      $this->assertEquals(
        'z', 
        str_replace('Z', 'z', new Character('Z')
      ));
    }
  }
?>
