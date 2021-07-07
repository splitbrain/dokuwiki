<?php
/**
* @version $Id: lexer.todo.php,v 1.2 2005/03/25 21:00:22 harryf Exp $
* @package Doku
* @subpackage Tests
*/

use dokuwiki\Parsing\Lexer\Lexer;
use dokuwiki\Parsing\Lexer\ParallelRegex;
use dokuwiki\Parsing\Lexer\StateStack;

/**
* @package Doku
* @subpackage Tests
*/
class TestOfLexerParallelRegex extends DokuWikiTest {
    function testNoPatterns() {
        $regex = new ParallelRegex(false);
        $this->assertFalse($regex->apply("Hello", $match));
        $this->assertEquals($match, "");
    }
    function testNoSubject() {
        $regex = new ParallelRegex(false);
        $regex->addPattern(".*");
        $this->assertTrue($regex->apply("", $match));
        $this->assertEquals($match, "");
    }
    function testMatchAll() {
        $regex = new ParallelRegex(false);
        $regex->addPattern(".*");
        $this->assertTrue($regex->apply("Hello", $match));
        $this->assertEquals($match, "Hello");
    }
    function testCaseSensitive() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("abc");
        $this->assertTrue($regex->apply("abcdef", $match));
        $this->assertEquals($match, "abc");
        $this->assertTrue($regex->apply("AAABCabcdef", $match));
        $this->assertEquals($match, "abc");
    }
    function testCaseInsensitive() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("abc");
        $this->assertTrue($regex->apply("abcdef", $match));
        $this->assertEquals($match, "abc");
        $this->assertTrue($regex->apply("AAABCabcdef", $match));
        $this->assertEquals($match, "ABC");
    }
    function testMatchMultiple() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("abc");
        $regex->addPattern("ABC");
        $this->assertTrue($regex->apply("abcdef", $match));
        $this->assertEquals($match, "abc");
        $this->assertTrue($regex->apply("AAABCabcdef", $match));
        $this->assertEquals($match, "ABC");
        $this->assertFalse($regex->apply("Hello", $match));
    }
    function testPatternLabels() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("abc", "letter");
        $regex->addPattern("123", "number");
        $this->assertEquals($regex->apply("abcdef", $match), "letter");
        $this->assertEquals($match, "abc");
        $this->assertEquals($regex->apply("0123456789", $match), "number");
        $this->assertEquals($match, "123");
    }
    function testMatchMultipleWithLookaheadNot() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("abc");
        $regex->addPattern("ABC");
        $regex->addPattern("a(?!\n).{1}");
        $this->assertTrue($regex->apply("abcdef", $match));
        $this->assertEquals($match, "abc");
        $this->assertTrue($regex->apply("AAABCabcdef", $match));
        $this->assertEquals($match, "ABC");
        $this->assertTrue($regex->apply("a\nab", $match));
        $this->assertEquals($match, "ab");
        $this->assertFalse($regex->apply("Hello", $match));
    }
    function testMatchSetOptionCaseless() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("a(?i)b(?i)c");
        $this->assertTrue($regex->apply("aBc", $match));
        $this->assertEquals($match, "aBc");
    }
    function testMatchSetOptionUngreedy() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("(?U)\w+");
        $this->assertTrue($regex->apply("aaaaaa", $match));
        $this->assertEquals($match, "a");
    }
    function testMatchLookaheadEqual() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("\w(?=c)");
        $this->assertTrue($regex->apply("xbyczd", $match));
        $this->assertEquals($match, "y");
    }
    function testMatchLookaheadNot() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("\w(?!b|c)");
        $this->assertTrue($regex->apply("xbyczd", $match));
        $this->assertEquals($match, "b");
    }
    function testMatchLookbehindEqual() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("(?<=c)\w");
        $this->assertTrue($regex->apply("xbyczd", $match));
        $this->assertEquals($match, "z");
    }
    function testMatchLookbehindNot() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("(?<!\A|x|b)\w");
        $this->assertTrue($regex->apply("xbyczd", $match));
        $this->assertEquals($match, "c");
    }
    function testUnicodeCaseSensitive() {
        $regex = new ParallelRegex(true);
        $regex->addPattern("áêì");
        $this->assertTrue($regex->match("abcáêìdef", $match));
        $this->assertEquals($match, "áêì");
        $this->assertTrue($regex->match("AAAÁÊÌBCabcáêìdef", $match));
        $this->assertEquals($match, "áêì");
    }
    function testUnicodeCaseInsensitive() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("áêì");
        $this->assertTrue($regex->match("abcáêìdef", $match));
        $this->assertEquals($match, "áêì");
        $this->assertTrue($regex->match("AAAÁÊÌBCabcáêìdef", $match));
        $this->assertEquals($match, "ÁÊÌ");
    }
    function testUnicodeSequenceComposed() {
        $regex = new ParallelRegex(false);
        $regex->addPattern('abc\X\X\Xdef'); // \Xde is interpreted with double quotes
        $this->assertTrue($regex->match("xyzabcáêìdef123", $match));
        $this->assertEquals($match, "abcáêìdef");
    }
    function testUnicodeSequenceDecomposed() {
        $regex = new ParallelRegex(false);
        $regex->addPattern('abc\X\X\Xdef'); // \Xde is interpreted with double quotes
        $accents = "a"."́"."e"."̂"."i"."̀"; // áêì decomposed
        $this->assertTrue($regex->match("xyzabc" . $accents . "def123", $match));
        $this->assertEquals($match, "abc" . $accents . "def");
    }
    function testUnicodeWithProperty() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("\p{Greek}+"); // Greek characters
        $this->assertTrue($regex->match("abcαβγdef", $match));
        $this->assertEquals($match, "αβγ");
    }
    function testUnicodeWithoutProperty() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("\P{Greek}+"); // non-Greek characters
        $this->assertTrue($regex->match("αβγabcδεζ", $match));
        $this->assertEquals($match, "abc");
    }
    function testUnicodeWithPropertyGroup() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("\pL+"); // any letters
        $this->assertTrue($regex->match("123abcαβγdef456", $match));
        $this->assertEquals($match, "abcαβγdef");
    }
    function testUnicodeWithoutPropertyGroup() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("\PL+"); // anything except letters
        $this->assertTrue($regex->match("abcαβγ4.@def", $match));
        $this->assertEquals($match, "4.@");
    }
    function testUnicodeSequenceMultibyte() {
        $utf8OneByte    = "a"; // Latin (ASCII)
        $utf8TwoBytes   = "α"; // Greek
        $utf8ThreeBytes = "ァ"; // Japanese
        $utf8FourBytes  = "𐤀"; // Phoenician
        $this->assertEquals(strlen($utf8OneByte),    1); // sanity check
        $this->assertEquals(strlen($utf8TwoBytes),   2);
        $this->assertEquals(strlen($utf8ThreeBytes), 3);
        $this->assertEquals(strlen($utf8FourBytes),  4);

        $regex = new ParallelRegex(false);
        $regex->addPattern("z\Xp");
        $this->assertTrue($regex->match("abcz" . $utf8OneByte . "pdef", $match));
        $this->assertEquals($match, "z" . $utf8OneByte . "p");
        $this->assertTrue($regex->match("abcz" . $utf8TwoBytes . "pdef", $match));
        $this->assertEquals($match, "z" . $utf8TwoBytes . "p");
        $this->assertTrue($regex->match("abcz" . $utf8ThreeBytes . "pdef", $match));
        $this->assertEquals($match, "z" . $utf8ThreeBytes . "p");
        $this->assertTrue($regex->match("abcz" . $utf8FourBytes . "pdef", $match));
        $this->assertEquals($match, "z" . $utf8FourBytes . "p");
    }
    function testMixedPatterns() {
        $regex = new ParallelRegex(false);
        $regex->addPattern("<\w+>");
        $regex->addPattern("z\p{Greek}p");
        $this->assertTrue($regex->match("123<abc>zαpabc", $match));
        $this->assertEquals($match, "<abc>");
        $this->assertTrue($regex->match("123zαp<abc>abc", $match));
        $this->assertEquals($match, "zαp");
    }
}

class TestOfLexerStateStack extends DokuWikiTest {
    function testStartState() {
        $stack = new StateStack("one");
        $this->assertEquals($stack->getCurrent(), "one");
    }
    function testExhaustion() {
        $stack = new StateStack("one");
        $this->assertFalse($stack->leave());
    }
    function testStateMoves() {
        $stack = new StateStack("one");
        $stack->enter("two");
        $this->assertEquals($stack->getCurrent(), "two");
        $stack->enter("three");
        $this->assertEquals($stack->getCurrent(), "three");
        $this->assertTrue($stack->leave());
        $this->assertEquals($stack->getCurrent(), "two");
        $stack->enter("third");
        $this->assertEquals($stack->getCurrent(), "third");
        $this->assertTrue($stack->leave());
        $this->assertTrue($stack->leave());
        $this->assertEquals($stack->getCurrent(), "one");
    }
}

class TestParser {
    function __construct() {
    }
    function accept() {
    }
    function a() {
    }
    function b() {
    }
}

class TestOfLexer extends DokuWikiTest {
    function testNoPatterns() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->never())->method('accept');
        $lexer = new Lexer($handler);
        $this->assertFalse($lexer->parse("abcdef"));
    }
    function testEmptyPage() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->never())->method('accept');
        $lexer = new Lexer($handler);
        $lexer->addPattern("a+");
        $this->assertTrue($lexer->parse(""));
    }
    function testSinglePattern() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("aaa", DOKU_LEXER_MATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("x", DOKU_LEXER_UNMATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("a", DOKU_LEXER_MATCHED, 4)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('accept')
            ->with("yyy", DOKU_LEXER_UNMATCHED, 5)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('accept')
            ->with("a", DOKU_LEXER_MATCHED, 8)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('accept')
            ->with("x", DOKU_LEXER_UNMATCHED, 9)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('accept')
            ->with("aaa", DOKU_LEXER_MATCHED, 10)->will($this->returnValue(true));
        $handler->expects($this->at(7))->method('accept')
            ->with("z", DOKU_LEXER_UNMATCHED, 13)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("a+");
        $this->assertTrue($lexer->parse("aaaxayyyaxaaaz"));
    }
    function testMultiplePattern() {
        $handler = $this->createPartialMock('TestParser', array('accept'));
        $target = array("a", "b", "a", "bb", "x", "b", "a", "xxxxxx", "a", "x");
        $positions = array(0, 1, 2, 3, 5, 6, 7, 8, 14, 15);
        for ($i = 0; $i < count($target); $i++) {
            $handler->expects($this->at($i))->method('accept')
                ->with($target[$i], $this->anything(), $positions[$i])->will($this->returnValue(true));
        }
        $lexer = new Lexer($handler);
        $lexer->addPattern("a+");
        $lexer->addPattern("b+");
        $this->assertTrue($lexer->parse("ababbxbaxxxxxxax"));
    }
}

class TestOfUnicodeAwareLexer extends DokuWikiTest {
    function testSinglePatternAccentedLetters() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("abc", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("áêì", DOKU_LEXER_MATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("def", DOKU_LEXER_UNMATCHED, 9)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("áêì");
        $this->assertTrue($lexer->parse("abcáêìdef"));
    }
    function testSinglePatternUnicodeSequenceComposed() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("xyz", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("abcáêìdef", DOKU_LEXER_MATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("123", DOKU_LEXER_UNMATCHED, 15)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern('abc\X\X\Xdef'); // \Xde is interpreted with double quotes
        $this->assertTrue($lexer->parse("xyzabcáêìdef123"));
    }
    function testSinglePatternUnicodeSequenceDecomposed() {
        $accents = "a"."́"."e"."̂"."i"."̀"; // áêì decomposed

        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("xyz", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("abc" . $accents . "def", DOKU_LEXER_MATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("123", DOKU_LEXER_UNMATCHED, 18)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern('abc\X\X\Xdef'); // \Xde is interpreted with double quotes
        $this->assertTrue($lexer->parse("xyzabc" . $accents . "def123"));
    }
    function testSinglePatternUnicodeWithProperty() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("abc", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("αβγ", DOKU_LEXER_MATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("def", DOKU_LEXER_UNMATCHED, 9)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("\p{Greek}+"); // Greek characters
        $this->assertTrue($lexer->parse("abcαβγdef"));
    }
    function testSinglePatternUnicodeWithoutProperty() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("αβγ", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("abc", DOKU_LEXER_MATCHED, 6)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("δεζ", DOKU_LEXER_UNMATCHED, 9)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("\P{Greek}+"); // non-Greek characters
        $this->assertTrue($lexer->parse("αβγabcδεζ"));
    }
    function testSinglePatternUnicodeWithPropertyGroup() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("123", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("abcαβγdef", DOKU_LEXER_MATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("456", DOKU_LEXER_UNMATCHED, 15)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("\pL+"); // any letters
        $this->assertTrue($lexer->parse("123abcαβγdef456"));
    }
    function testSinglePatternUnicodeWithoutPropertyGroup() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("abcαβγ", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("4.@", DOKU_LEXER_MATCHED, 9)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("def", DOKU_LEXER_UNMATCHED, 12)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("\PL+"); // anything except letters
        $this->assertTrue($lexer->parse("abcαβγ4.@def"));
    }
    function testSinglePatternUnicodeSequenceMultibyte() {
        $utf8OneByte    = "a"; // Latin (ASCII)
        $utf8TwoBytes   = "α"; // Greek
        $utf8ThreeBytes = "ァ"; // Japanese
        $utf8FourBytes  = "𐤀"; // Phoenician
        $this->assertEquals(strlen($utf8OneByte),    1); // sanity check
        $this->assertEquals(strlen($utf8TwoBytes),   2);
        $this->assertEquals(strlen($utf8ThreeBytes), 3);
        $this->assertEquals(strlen($utf8FourBytes),  4);

        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("abc", DOKU_LEXER_UNMATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("z" . $utf8OneByte . "p", DOKU_LEXER_MATCHED, 3)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("defabc", DOKU_LEXER_UNMATCHED, 6)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('accept')
            ->with("z" . $utf8TwoBytes . "p", DOKU_LEXER_MATCHED, 12)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('accept')
            ->with("defabc", DOKU_LEXER_UNMATCHED, 16)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('accept')
            ->with("z" . $utf8ThreeBytes . "p", DOKU_LEXER_MATCHED, 22)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('accept')
            ->with("defabc", DOKU_LEXER_UNMATCHED, 27)->will($this->returnValue(true));
        $handler->expects($this->at(7))->method('accept')
            ->with("z" . $utf8FourBytes . "p", DOKU_LEXER_MATCHED, 33)->will($this->returnValue(true));
        $handler->expects($this->at(8))->method('accept')
            ->with("def", DOKU_LEXER_UNMATCHED, 39)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("z\Xp");
        $this->assertTrue($lexer->parse("abcz" . $utf8OneByte    . "pdef" .
                                        "abcz" . $utf8TwoBytes   . "pdef" .
                                        "abcz" . $utf8ThreeBytes . "pdef" .
                                        "abcz" . $utf8FourBytes  . "pdef"));
    }
    function testMixedPatterns() {
        $utf8OneByte    = "a"; // Latin (ASCII)
        $utf8TwoBytes   = "α"; // Greek
        $utf8ThreeBytes = "ァ"; // Japanese
        $utf8FourBytes  = "𐤀"; // Phoenician
        $this->assertEquals(strlen($utf8OneByte),    1); // sanity check
        $this->assertEquals(strlen($utf8TwoBytes),   2);
        $this->assertEquals(strlen($utf8ThreeBytes), 3);
        $this->assertEquals(strlen($utf8FourBytes),  4);

        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('accept')
            ->with("<abc>", DOKU_LEXER_MATCHED, 0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('accept')
            ->with("z" . $utf8OneByte . "p", DOKU_LEXER_MATCHED, 5)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('accept')
            ->with("abc", DOKU_LEXER_UNMATCHED, 8)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('accept')
            ->with("<def>", DOKU_LEXER_MATCHED, 11)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('accept')
            ->with("z" . $utf8TwoBytes . "p", DOKU_LEXER_MATCHED, 16)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('accept')
            ->with("def", DOKU_LEXER_UNMATCHED, 20)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('accept')
            ->with("<ghi>", DOKU_LEXER_MATCHED, 23)->will($this->returnValue(true));
        $handler->expects($this->at(7))->method('accept')
            ->with("z" . $utf8ThreeBytes . "p", DOKU_LEXER_MATCHED, 28)->will($this->returnValue(true));
        $handler->expects($this->at(8))->method('accept')
            ->with("ghi", DOKU_LEXER_UNMATCHED, 33)->will($this->returnValue(true));
        $handler->expects($this->at(9))->method('accept')
            ->with("<jkl>", DOKU_LEXER_MATCHED, 36)->will($this->returnValue(true));
        $handler->expects($this->at(10))->method('accept')
            ->with("z" . $utf8FourBytes . "p", DOKU_LEXER_MATCHED, 41)->will($this->returnValue(true));
        $handler->expects($this->at(11))->method('accept')
            ->with("jkl", DOKU_LEXER_UNMATCHED, 47)->will($this->returnValue(true));

        $lexer = new Lexer($handler);
        $lexer->addPattern("<\w+>");
        $lexer->addPattern("z\Xp");
        $this->assertTrue($lexer->parse("<abc>z" . $utf8OneByte    . "pabc" .
                                        "<def>z" . $utf8TwoBytes   . "pdef" .
                                        "<ghi>z" . $utf8ThreeBytes . "pghi" .
                                        "<jkl>z" . $utf8FourBytes  . "pjkl"));
    }
}

class TestOfLexerModes extends DokuWikiTest {
    function testIsolatedPattern() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('a')
            ->with("a", DOKU_LEXER_MATCHED,0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,1)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,2)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('a')
            ->with("bxb", DOKU_LEXER_UNMATCHED,4)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('a')
            ->with("aaa", DOKU_LEXER_MATCHED,7)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('a')
            ->with("x", DOKU_LEXER_UNMATCHED,10)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('a')
            ->with("aaaa", DOKU_LEXER_MATCHED,11)->will($this->returnValue(true));
        $handler->expects($this->at(7))->method('a')
            ->with("x", DOKU_LEXER_UNMATCHED,15)->will($this->returnValue(true));
        $lexer = new Lexer($handler, "a");
        $lexer->addPattern("a+", "a");
        $lexer->addPattern("b+", "b");
        $this->assertTrue($lexer->parse("abaabxbaaaxaaaax"));
    }
    function testModeChange() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('a')
            ->with("a", DOKU_LEXER_MATCHED,0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,1)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,2)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,4)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('a')
            ->with("aaa", DOKU_LEXER_MATCHED,5)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('b')
            ->with(":", DOKU_LEXER_ENTER,8)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('b')
            ->with("a", DOKU_LEXER_UNMATCHED,9)->will($this->returnValue(true));
        $handler->expects($this->at(7))->method('b')
            ->with("b", DOKU_LEXER_MATCHED, 10)->will($this->returnValue(true));
        $handler->expects($this->at(8))->method('b')
            ->with("a", DOKU_LEXER_UNMATCHED,11)->will($this->returnValue(true));
        $handler->expects($this->at(9))->method('b')
            ->with("bb", DOKU_LEXER_MATCHED,12)->will($this->returnValue(true));
        $handler->expects($this->at(10))->method('b')
            ->with("a", DOKU_LEXER_UNMATCHED,14)->will($this->returnValue(true));
        $handler->expects($this->at(11))->method('b')
            ->with("bbb", DOKU_LEXER_MATCHED,15)->will($this->returnValue(true));
        $handler->expects($this->at(12))->method('b')
            ->with("a", DOKU_LEXER_UNMATCHED,18)->will($this->returnValue(true));

        $lexer = new Lexer($handler, "a");
        $lexer->addPattern("a+", "a");
        $lexer->addEntryPattern(":", "a", "b");
        $lexer->addPattern("b+", "b");
        $this->assertTrue($lexer->parse("abaabaaa:ababbabbba"));
    }
    function testNesting() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,2)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,3)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,5)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('b')
            ->with("(", DOKU_LEXER_ENTER,6)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('b')
            ->with("bb", DOKU_LEXER_MATCHED,7)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('b')
            ->with("a", DOKU_LEXER_UNMATCHED,9)->will($this->returnValue(true));
        $handler->expects($this->at(7))->method('b')
            ->with("bb", DOKU_LEXER_MATCHED,10)->will($this->returnValue(true));
        $handler->expects($this->at(8))->method('b')
            ->with(")", DOKU_LEXER_EXIT,12)->will($this->returnValue(true));
        $handler->expects($this->at(9))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,13)->will($this->returnValue(true));
        $handler->expects($this->at(10))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,15)->will($this->returnValue(true));


        $lexer = new Lexer($handler, "a");
        $lexer->addPattern("a+", "a");
        $lexer->addEntryPattern("(", "a", "b");
        $lexer->addPattern("b+", "b");
        $lexer->addExitPattern(")", "b");
        $this->assertTrue($lexer->parse("aabaab(bbabb)aab"));
    }
    function testSingular() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('b')
            ->with("b", DOKU_LEXER_SPECIAL,2)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,3)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('a')
            ->with("xx", DOKU_LEXER_UNMATCHED,5)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('b')
            ->with("bbb", DOKU_LEXER_SPECIAL,7)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('a')
            ->with("xx", DOKU_LEXER_UNMATCHED,10)->will($this->returnValue(true));
        $lexer = new Lexer($handler, "a");
        $lexer->addPattern("a+", "a");
        $lexer->addSpecialPattern("b+", "a", "b");
        $this->assertTrue($lexer->parse("aabaaxxbbbxx"));
    }
    function testUnwindTooFar() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('a')
            ->with(")", DOKU_LEXER_EXIT,2)->will($this->returnValue(true));

        $lexer = new Lexer($handler, "a");
        $lexer->addPattern("a+", "a");
        $lexer->addExitPattern(")", "a");
        $this->assertFalse($lexer->parse("aa)aa"));
    }
}

class TestOfLexerHandlers extends DokuWikiTest {
    function testModeMapping() {
        $handler = $this->createMock('TestParser');
        $handler->expects($this->at(0))->method('a')
            ->with("aa", DOKU_LEXER_MATCHED,0)->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('a')
            ->with("(", DOKU_LEXER_ENTER,2)->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('a')
            ->with("bb", DOKU_LEXER_MATCHED,3)->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('a')
            ->with("a", DOKU_LEXER_UNMATCHED,5)->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('a')
            ->with("bb", DOKU_LEXER_MATCHED,6)->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('a')
            ->with(")", DOKU_LEXER_EXIT,8)->will($this->returnValue(true));
        $handler->expects($this->at(6))->method('a')
            ->with("b", DOKU_LEXER_UNMATCHED,9)->will($this->returnValue(true));

        $lexer = new Lexer($handler, "mode_a");
        $lexer->addPattern("a+", "mode_a");
        $lexer->addEntryPattern("(", "mode_a", "mode_b");
        $lexer->addPattern("b+", "mode_b");
        $lexer->addExitPattern(")", "mode_b");
        $lexer->mapHandler("mode_a", "a");
        $lexer->mapHandler("mode_b", "a");
        $this->assertTrue($lexer->parse("aa(bbabb)b"));
    }
}

class TestParserByteIndex {

    function __construct() {}

    function ignore() {}

    function caught() {}
}

class TestOfLexerByteIndices extends DokuWikiTest {

    function testIndex() {
        $doc = "aaa<file>bcd</file>eee";

        $handler = $this->createMock('TestParserByteIndex');
        $handler->expects($this->any())->method('ignore')->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('caught')
            ->with("<file>", DOKU_LEXER_ENTER, strpos($doc,'<file>'))->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('caught')
            ->with("b", DOKU_LEXER_SPECIAL, strpos($doc,'b'))->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('caught')
            ->with("c", DOKU_LEXER_MATCHED, strpos($doc,'c'))->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('caught')
            ->with("d", DOKU_LEXER_UNMATCHED, strpos($doc,'d'))->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('caught')
            ->with("</file>", DOKU_LEXER_EXIT, strpos($doc,'</file>'))->will($this->returnValue(true));

        $lexer = new Lexer($handler, "ignore");
        $lexer->addEntryPattern("<file>", "ignore", "caught");
        $lexer->addExitPattern("</file>", "caught");
        $lexer->addSpecialPattern('b','caught','special');
        $lexer->mapHandler('special','caught');
        $lexer->addPattern('c','caught');

        $this->assertTrue($lexer->parse($doc));
    }

    function testIndexLookaheadEqual() {
        $doc = "aaa<file>bcd</file>eee";

        $handler = $this->createMock('TestParserByteIndex');
        $handler->expects($this->any())->method('ignore')->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('caught')
            ->with("<file>", DOKU_LEXER_ENTER, strpos($doc,'<file>'))->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('caught')
            ->with("b", DOKU_LEXER_SPECIAL, strpos($doc,'b'))->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('caught')
            ->with("c", DOKU_LEXER_MATCHED, strpos($doc,'c'))->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('caught')
            ->with("d", DOKU_LEXER_UNMATCHED, strpos($doc,'d'))->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('caught')
            ->with("</file>", DOKU_LEXER_EXIT, strpos($doc,'</file>'))->will($this->returnValue(true));

        $lexer = new Lexer($handler, "ignore");
        $lexer->addEntryPattern('<file>(?=.*</file>)', "ignore", "caught");
        $lexer->addExitPattern("</file>", "caught");
        $lexer->addSpecialPattern('b','caught','special');
        $lexer->mapHandler('special','caught');
        $lexer->addPattern('c','caught');

        $this->assertTrue($lexer->parse($doc));
    }

    function testIndexLookaheadNotEqual() {
        $doc = "aaa<file>bcd</file>eee";

        $handler = $this->createMock('TestParserByteIndex');
        $handler->expects($this->any())->method('ignore')->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('caught')
            ->with("<file>", DOKU_LEXER_ENTER, strpos($doc,'<file>'))->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('caught')
            ->with("b", DOKU_LEXER_SPECIAL, strpos($doc,'b'))->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('caught')
            ->with("c", DOKU_LEXER_MATCHED, strpos($doc,'c'))->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('caught')
            ->with("d", DOKU_LEXER_UNMATCHED, strpos($doc,'d'))->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('caught')
            ->with("</file>", DOKU_LEXER_EXIT, strpos($doc,'</file>'))->will($this->returnValue(true));

        $lexer = new Lexer($handler, "ignore");
        $lexer->addEntryPattern('<file>(?!foo)', "ignore", "caught");
        $lexer->addExitPattern("</file>", "caught");
        $lexer->addSpecialPattern('b','caught','special');
        $lexer->mapHandler('special','caught');
        $lexer->addPattern('c','caught');

        $this->assertTrue($lexer->parse($doc));
    }

    function testIndexLookbehindEqual() {
        $doc = "aaa<file>bcd</file>eee";

        $handler = $this->createMock('TestParserByteIndex');
        $handler->expects($this->any())->method('ignore')->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('caught')
            ->with("<file>", DOKU_LEXER_ENTER, strpos($doc,'<file>'))->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('caught')
            ->with("b", DOKU_LEXER_SPECIAL, strpos($doc,'b'))->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('caught')
            ->with("c", DOKU_LEXER_MATCHED, strpos($doc,'c'))->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('caught')
            ->with("d", DOKU_LEXER_UNMATCHED, strpos($doc,'d'))->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('caught')
            ->with("</file>", DOKU_LEXER_EXIT, strpos($doc,'</file>'))->will($this->returnValue(true));

        $lexer = new Lexer($handler, "ignore");
        $lexer->addEntryPattern('<file>', "ignore", "caught");
        $lexer->addExitPattern("(?<=d)</file>", "caught");
        $lexer->addSpecialPattern('b','caught','special');
        $lexer->mapHandler('special','caught');
        $lexer->addPattern('c','caught');

        $this->assertTrue($lexer->parse($doc));
    }

    function testIndexLookbehindNotEqual() {
        $doc = "aaa<file>bcd</file>eee";

        $handler = $this->createMock('TestParserByteIndex');
        $handler->expects($this->any())->method('ignore')->will($this->returnValue(true));
        $handler->expects($this->at(1))->method('caught')
            ->with("<file>", DOKU_LEXER_ENTER, strpos($doc,'<file>'))->will($this->returnValue(true));
        $handler->expects($this->at(2))->method('caught')
            ->with("b", DOKU_LEXER_SPECIAL, strpos($doc,'b'))->will($this->returnValue(true));
        $handler->expects($this->at(3))->method('caught')
            ->with("c", DOKU_LEXER_MATCHED, strpos($doc,'c'))->will($this->returnValue(true));
        $handler->expects($this->at(4))->method('caught')
            ->with("d", DOKU_LEXER_UNMATCHED, strpos($doc,'d'))->will($this->returnValue(true));
        $handler->expects($this->at(5))->method('caught')
            ->with("</file>", DOKU_LEXER_EXIT, strpos($doc,'</file>'))->will($this->returnValue(true));

        $lexer = new Lexer($handler, 'ignore');
        $lexer->addEntryPattern('<file>', 'ignore', 'caught');
        $lexer->addExitPattern('(?<!c)</file>', 'caught');
        $lexer->addSpecialPattern('b','caught','special');
        $lexer->mapHandler('special','caught');
        $lexer->addPattern('c','caught');

        $this->assertTrue($lexer->parse($doc));
    }

    /**
     * This test is primarily to ensure the correct match is chosen
     * when there are non-captured elements in the pattern.
     */
    function testIndexSelectCorrectMatch() {
        $doc = "ALL FOOLS ARE FOO";
        $pattern = '\bFOO\b';

        $handler = $this->createMock('TestParserByteIndex');
        $handler->expects($this->any())->method('ignore')->will($this->returnValue(true));

        $matches = array();
        preg_match('/'.$pattern.'/',$doc,$matches,PREG_OFFSET_CAPTURE);

        $handler->expects($this->once())->method('caught')
            ->with("FOO", DOKU_LEXER_SPECIAL, $matches[0][1])->will($this->returnValue(true));

        $lexer = new Lexer($handler, "ignore");
        $lexer->addSpecialPattern($pattern,'ignore','caught');

        $this->assertTrue($lexer->parse($doc));
    }

}
