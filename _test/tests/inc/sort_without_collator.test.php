<?php

use dokuwiki\Utf8\Sort;

require_once __DIR__ . '/sort_with_collator.test.php';

/**
 * Based on sort_with_collator.test.php.
 *
 * @author Moisés Braga Ribeiro <moisesbr@gmail.com>
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class sort_without_collator_test extends sort_with_collator_test
{
    /**
     * Disable the "intl" extension.
     */
    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();
        Sort::useIntl(false);
    }

    /**
     * Reenable the "intl" extension.
     */
    public static function tearDownAfterClass() : void
    {
        Sort::useIntl(true);
        parent::tearDownAfterClass();
    }

    /**
     * Since we always use the fallback sort, we do not check for
     * the availability of the "intl" extension here at all.
     */
    public function testIntlExtensionAvailability()
    {
        $this->assertTrue(true); // avoid being marked as risky for having no assertion
    }

    /**
     * Provide real word pairs of the languages being tested (when possible).
     * The pairs should show what the fallback sort can or cannot do, as it
     * simply follows character codes.
     *
     * In particular, there should be a test to show that every character with
     * an accent (diacritic) used in the language is WRONGLY sorted after Z.
     *
     * @return Generator|array
     * @see testStrcmp
     */
    public function provideWordPairs()
    {
        static $pairs = [
            // Esperanto
            'eo' => [
                // fallback sort works for c < ĉ, but not for ĉ < d (and so on)
                ['celo', 'ĉapo'], ['glacio', 'ĝirafo'], ['horo', 'ĥameleono'],
                ['jes', 'ĵaŭdo'], ['seka', 'ŝako'], ['urso', 'ŭaŭ'],
                // fallback sort WRONGLY puts ĉ/ĝ/ĥ/ĵ/ŝ/ŭ after z
                ['zorio', 'ĉokolado'], ['zorio', 'ĝojo'], ['zorio', 'ĥoro'],
                ['zorio', 'ĵurnalo'], ['zorio', 'ŝuo'], ['zorio', 'ŭo'],
                // natural sort works as usual
                ['paĝo 2', 'paĝo 10'], ['paĝo 51', 'paĝo 100']
            ],

            // German
            'de' => [
                // fallback sort WRONGLY puts ä/ö/ü/ß after z
                ['Zebra', 'Äpfel'], ['Zebra', 'Öl'], ['Zebra', 'Übersetzung'],
                ['Weizen', 'weiß'],
                // natural sort works as usual
                ['Seite 2', 'Seite 10'], ['Seite 51', 'Seite 100']
            ],

            // Portuguese
            'pt' => [
                // fallback sort WRONGLY puts accented letters after z
                ['zebra', 'às'], ['zebra', 'água'], ['zebra', 'âmbar'],
                ['zebra', 'épico'], ['zebra', 'ênclise'], ['zebra', 'índio'],
                ['zebra', 'ótimo'], ['zebra', 'ônibus'], ['zebra', 'último'],
                ['pizza', 'pião'], ['pizza', 'piões'], ['azar', 'aço'],
                // natural sort works as usual
                ['página 2', 'página 10'], ['página 51', 'página 100']
            ],

            // Spanish
            'es' => [
                // fallback sort works for n < ñ, but not for ñ < o
                ['nube', 'ñu'],
                // fallback sort WRONGLY puts accented letters after z
                ['zapato', 'ácido'], ['zapato', 'él'], ['zapato', 'íntimo'],
                ['zapato', 'óptimo'], ['zapato', 'último'],
                ['pizza', 'piña'],
                // natural sort works as usual
                ['página 2', 'página 10'], ['página 51', 'página 100']
            ],
        ];

        foreach ($pairs as $lang => $list) {
            foreach ($list as $pair) {
                yield [$lang, $pair[0], $pair[1]];
            }
        }
    }

    /**
     * Provide WRONG sorted sequences of all characters used in the languages
     * being tested, as the fallback sort simply follows character codes.
     *
     * The sorted sequences given in class "sort_with_collator" are simply
     * reordered here, starting with A-Z and continuing with accented characters
     * ordered by character codes.
     *
     * @return Generator|array
     * @see testSort
     * @see testKSort
     * @see testASort
     * @see testASortFnUrl
     * @see testASortFnSafe
     * @see testASortFnUtf8
     */
    public function provideSortedCharList()
    {
        static $lists = [
            // Esperanto
            //      'a b c ĉ d e f g ĝ h ĥ i j ĵ k l m n o p r s ŝ t u ŭ v z'
            'eo' => 'a b c d e f g h i j k l m n o p r s t u v z ĉ ĝ ĥ ĵ ŝ ŭ',

            // German
            //      'a ä b c d e f g h i j k l m n o ö p q r s ß t u ü v w x y z'
            'de' => 'a b c d e f g h i j k l m n o p q r s t u v w x y z ß ä ö ü',

            // Portuguese
            //      'a á à â ã b c ç d e é ê f g h i í j k l m n o ó ô õ p q r s t u ú ü v w x y z'
            'pt' => 'a b c d e f g h i j k l m n o p q r s t u v w x y z à á â ã ç é ê í ó ô õ ú ü',

            // Spanish
            //      'a á b c d e é f g h i í j k l m n ñ o ó p q r s t u ú v w x y z'
            'es' => 'a b c d e f g h i j k l m n o p q r s t u v w x y z á é í ñ ó ú',
        ];

        foreach ($lists as $lang => $list) {
            yield [$lang, $list];
        }
    }
}
