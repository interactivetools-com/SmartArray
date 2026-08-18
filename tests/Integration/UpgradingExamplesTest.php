<?php
/** @noinspection PhpDeprecationInspection, PhpUndefinedMethodInspection, PhpNamedArgumentsInspection */
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Integration;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use Itools\SmartString\SmartString;
use Error;
use InvalidArgumentException;

/**
 * Every checkable claim in UPGRADING.md, run against the current code.
 *
 * UPGRADING is the file people follow while migrating, so a stale claim there
 * costs someone a debugging session. Each test pins one statement from the
 * document: the before/after examples, the "silent changes" bullets, and the
 * old names the document promises still work.
 *
 * Older sections describe what changed in past releases; only their "after"
 * half is checkable here, and that is what these tests pin.
 *
 * Where the document and the code disagree, the test pins the ACTUAL behavior
 * and a "DOCS MISMATCH" comment records the disagreement until the document is
 * fixed - same convention as DocsExamplesTest.
 *
 * Skipped, and why:
 * - or404(), orDie(), orRedirect() when they fire (they exit or send headers)
 * - the SmartArray-vs-SmartArrayHtml type-hint section, covered by
 *   DocsExamplesTest's without-smartstrings tests
 * - the $onOffsetAccess = 'log' escape hatch, covered by Unit/GlobalSettingsTest
 * - regex search strings (guidance for the reader's editor, not behavior)
 */
class UpgradingExamplesTest extends SmartArrayTestCase
{
    //region v3.0.0: Boolean argument to new()

    /** UPGRADING "Boolean argument to new()" - the contradicting boolean throws and names the fix. */
    public function testContradictingBooleanOnNewThrowsAndNamesTheReplacement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create SmartArray with useSmartStrings=true. Use SmartArrayHtml::new($data) instead.');

        SmartArray::new([['id' => 1]], true);
    }

    /** UPGRADING "Boolean argument to new()" - the corrected line returns an HTML-mode collection. */
    public function testSmartArrayHtmlNewIsTheReplacementForTheBooleanForm(): void
    {
        $rows = SmartArrayHtml::new([['name' => "Jean O'Brien"]]);

        $this->assertSame('Jean O&apos;Brien', (string)$rows->first()->name);
    }

    /** UPGRADING "Boolean argument to new()" - redundant booleans deprecate and keep working. */
    public function testRedundantBooleansDeprecateButStillBuildTheCollection(): void
    {
        [$raw, $rawMessages] = $this->captureDeprecations(static fn() => SmartArray::new([['id' => 1]], false));
        [$html, $htmlMessages] = $this->captureDeprecations(static fn() => SmartArrayHtml::new([['id' => 1]], true));

        $this->assertInstanceOf(SmartArray::class, $raw);
        $this->assertInstanceOf(SmartArrayHtml::class, $html);
        $this->assertStringContainsString('Passing false to SmartArray is deprecated', $rawMessages[0]);
        $this->assertStringContainsString('Passing true to SmartArrayHtml is deprecated', $htmlMessages[0]);
    }

    //endregion
    //region v3.0.0: Removed methods

    /** UPGRADING "Removed methods" - usingSmartStrings() is gone; the class is the mode. */
    public function testUsingSmartStringsIsRemovedAndTheClassAnswersInstead(): void
    {
        $this->assertFalse(method_exists(SmartArray::class, 'usingSmartStrings'));
        $this->assertFalse(method_exists(SmartArrayHtml::class, 'usingSmartStrings'));

        $this->assertInstanceOf(SmartArrayHtml::class, SmartArrayHtml::new([['id' => 1]]));
        $this->assertNotInstanceOf(SmartArrayHtml::class, SmartArray::new([['id' => 1]]));
    }

    /** UPGRADING "Removed methods" - setLoadHandler() is gone; the constructor property replaces it. */
    public function testSetLoadHandlerIsRemovedAndTheConstructorPropertyReplacesIt(): void
    {
        $this->assertFalse(method_exists(SmartArray::class, 'setLoadHandler'));

        $handler = static fn(): array => [];
        $rows    = new SmartArray([['id' => 1]], ['loadHandler' => $handler]);

        $this->assertInstanceOf(SmartArray::class, $rows);
    }

    /** UPGRADING "Removed methods" - newSmartNull() is no longer callable from outside. */
    public function testNewSmartNullIsNoLongerPublic(): void
    {
        $this->assertTrue(method_exists(SmartArray::class, 'newSmartNull'), 'still exists, just not public');
        $this->assertFalse((new \ReflectionMethod(SmartArray::class, 'newSmartNull'))->isPublic());
    }

    //endregion
    //region v3.0.0: sortBy() parameter rename

    /** UPGRADING "sortBy() parameter renamed" - the old named argument fails, the new one sorts. */
    public function testSortByTakesFlagsNotType(): void
    {
        $rows = SmartArray::new([['name' => 'a10'], ['name' => 'a2']]);

        $this->assertSame(['a2', 'a10'], $rows->sortBy('name', flags: SORT_NATURAL)->column('name')->toArray());

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown named parameter $type');
        $rows->sortBy('name', type: SORT_NATURAL);
    }

    //endregion
    //region v3.0.0: stored NULL reads as missing

    /** UPGRADING "stored NULL reads as missing" - the three checks on a stored null, in HTML mode. */
    public function testStoredNullReadsAsMissingToIssetEmptyAndNullCoalescing(): void
    {
        $row = SmartArrayHtml::new(['nickname' => null]);

        $this->assertSame('none', $row->nickname ?? 'none');
        $this->assertFalse(isset($row->nickname));
        $this->assertTrue(empty($row->nickname));
    }

    /** UPGRADING "stored NULL reads as missing" - bracket syntax answers the same way. */
    public function testBracketSyntaxTreatsStoredNullAsMissingToo(): void
    {
        $row = SmartArrayHtml::new(['nickname' => null]);

        $this->assertFalse(isset($row['nickname']));
    }

    /** UPGRADING "stored NULL reads as missing" - direct access still returns the stored null, wrapped, with no warning. */
    public function testDirectAccessStillReturnsTheStoredNullWithoutWarning(): void
    {
        $row = SmartArrayHtml::new(['nickname' => null]);

        [$field, $warnings] = $this->captureErrors(static fn() => $row->nickname, E_USER_WARNING);

        $this->assertInstanceOf(SmartString::class, $field);
        $this->assertNull($field->value());
        $this->assertSame([], $warnings);
    }

    /** UPGRADING "stored NULL reads as missing" - the documented way to ask "does the key exist, even if NULL". */
    public function testKeysContainsAnswersWhetherANullColumnExists(): void
    {
        $row = SmartArrayHtml::new(['nickname' => null]);

        $this->assertTrue($row->keys()->contains('nickname'));
        $this->assertFalse($row->keys()->contains('missing'));
    }

    //endregion
    //region v3.0.0: matching rules

    /** UPGRADING "Matching rules" - numbers still match numeric strings. */
    public function testNumbersStillMatchNumericStrings(): void
    {
        $this->assertCount(1, SmartArray::new([['id' => '5']])->where('id', 5));
        $this->assertCount(1, SmartArray::new([['price' => '1.00']])->where('price', 1));
    }

    /** UPGRADING "Matching rules" - look-alike numeric strings no longer match each other. */
    public function testNumericLookingStringsMustMatchExactly(): void
    {
        $rows = SmartArray::new([['code' => '0e999'], ['code' => '0e123'], ['code' => '01'], ['code' => '1']]);

        $this->assertSame([['code' => '0e123']], array_values($rows->where('code', '0e123')->toArray()));
        $this->assertSame([['code' => '1']], array_values($rows->where('code', '1')->toArray()));
    }

    /** UPGRADING "Matching rules" - null matches only null, like SQL IS NULL. */
    public function testWhereNullMatchesOnlyNull(): void
    {
        $rows = SmartArray::new([['f' => null], ['f' => ''], ['f' => 0], ['f' => false]]);

        $this->assertSame([['f' => null]], array_values($rows->where('f', null)->toArray()));
    }

    /** UPGRADING "Matching rules" - true means 1, so 'abc' no longer matches. */
    public function testWhereTrueMeansOneAndDropsOtherTruthyValues(): void
    {
        $rows = SmartArray::new([['x' => 1], ['x' => '1'], ['x' => 'abc'], ['x' => true]]);

        $this->assertSame([['x' => 1], ['x' => '1'], ['x' => true]], array_values($rows->where('x', true)->toArray()));
    }

    //endregion
    //region v3.0.0: throwing cases

    /** UPGRADING "Float key values throw" - the float-key error, message included. */
    public function testFloatKeyValuesThrowWithTheDocumentedMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("indexBy(): 'price' has float values, convert them to strings first");

        SmartArray::new([['price' => 19.99], ['price' => 19.50]])->indexBy('price');
    }

    /** UPGRADING "Row-only methods throw on mixed arrays" - the mixed-array error, message included. */
    public function testMixedArraysThrowAndNameTheOffendingElement(): void
    {
        $data = SmartArrayHtml::new(['count' => 5, 'items' => [['id' => 1]]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("where(): Expected a nested array of rows, but element 'count' is not a row (int)");

        $data->where('id', 1);
    }

    //endregion
    //region v3.0.0: silent changes

    /** UPGRADING "Silent changes" - print_r shows the data only, no injected pseudo-entries. */
    public function testPrintRShowsOnlyTheArrayData(): void
    {
        $output = print_r(SmartArrayHtml::new(['a' => 1]), true);

        $this->assertStringContainsString('[a] => 1', $output);
        $this->assertStringNotContainsString('useSmartStrings', $output);
        $this->assertStringNotContainsString('README', $output);
    }

    /** UPGRADING "Silent changes" - rows missing the index field key under "". */
    public function testIndexByKeysRowsMissingTheFieldUnderEmptyString(): void
    {
        $indexed = SmartArray::new([['a' => 1], ['b' => 2]])->indexBy('a');

        $this->assertSame([1, ''], array_keys($indexed->toArray()));
    }

    /** UPGRADING "Silent changes" - rows missing the sort field sort first. */
    public function testSortByPlacesRowsMissingTheFieldFirst(): void
    {
        $sorted = SmartArray::new([['s' => 'b'], ['x' => 9], ['s' => 'a']])->sortBy('s');

        $this->assertSame([['x' => 9], ['s' => 'a'], ['s' => 'b']], array_values($sorted->toArray()));
    }

    /** UPGRADING "Silent changes" - Smart values unwrap on the way in instead of throwing. */
    public function testSetAcceptsSmartValuesAndUnwrapsThem(): void
    {
        $source = SmartArrayHtml::new(['x' => "O'Brien"]);
        $target = SmartArray::new(['a' => 1]);

        $target->set('b', $source->x);
        $target->c = $source->x;

        $this->assertSame(['a' => 1, 'b' => "O'Brien", 'c' => "O'Brien"], $target->toArray());
    }

    /** UPGRADING "Silent changes" - load() throws InvalidArgumentException on a bad field name. */
    public function testLoadThrowsInvalidArgumentExceptionOnAnInvalidFieldName(): void
    {
        $rows = new SmartArray([['a' => 1]], ['loadHandler' => static fn(): array => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field name contains invalid characters: bad!name');

        $rows->load('bad!name');
    }

    //endregion
    //region v2.7.0

    /** UPGRADING "Parameter renames" - the old named argument fails, the new one passes through. */
    public function testOrMethodsTakeTextNotMessage(): void
    {
        $rows = SmartArray::new([['id' => 1]]);

        $this->assertInstanceOf(SmartArray::class, $rows->orThrow(text: 'Not found'));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown named parameter $message');
        $rows->orThrow(message: 'Not found');
    }

    /** UPGRADING "Silent changes" - malformed UTF-8 becomes U+FFFD instead of a false return. */
    public function testJsonEncodeSubstitutesMalformedBytesInsteadOfFailing(): void
    {
        $json = json_encode(SmartArray::new(['b' => "caf\xE9"]));

        $this->assertNotFalse($json, 'a corrupt byte used to fail the whole encode');
        $this->assertSame(['b' => "caf\u{FFFD}"], json_decode((string)$json, true));
    }

    //endregion
    //region v2.6.7

    /** UPGRADING "$array[.key.] access prints a deprecation notice" - bracket access works and deprecates; property syntax does not. */
    public function testBracketAccessStillWorksAndRaisesADeprecation(): void
    {
        $row = SmartArrayHtml::new(['name' => 'Bob', 'users.id' => 7]);

        // The default 'notify' mode echoes the notice into the page as well as triggering it
        [[$value, $messages], $echoed] = $this->captureOutput(fn() => $this->captureDeprecations(static fn() => (string)$row['name']));

        $this->assertSame('Bob', $value);
        $this->assertStringContainsString("Replace ['name'] with ->name", $messages[0]);
        $this->assertStringContainsString('Deprecated:', $echoed);

        [, $braceMessages] = $this->captureDeprecations(static fn() => (string)$row->{'users.id'});
        $this->assertSame([], $braceMessages, 'brace syntax is the documented replacement, it must not deprecate');
    }

    /** UPGRADING "Removed settings" - the three removed settings fail loudly. */
    public function testRemovedSettingsAreUndeclared(): void
    {
        foreach (['warnIfMissing', 'warnIfDeprecated', 'logDeprecations'] as $setting) {
            $this->assertFalse(
                property_exists(SmartArrayBase::class, $setting),
                "\$$setting was removed in v2.6.7; leftovers must fail with 'Access to undeclared static property'",
            );
        }
    }

    /** UPGRADING "Optional renames" - every old name in the renames table still works. */
    public function testRenamedMethodsStillWorkAndNameTheirReplacement(): void
    {
        [$raw, $rawMessages]   = $this->captureDeprecations(static fn() => SmartArrayHtml::new([['a' => 1]])->toRaw());
        [$html, $htmlMessages] = $this->captureDeprecations(static fn() => SmartArray::new([['a' => 1]])->toHtml());
        [$mapped, $mapMessages] = $this->captureDeprecations(static fn() => SmartArray::new([1, 2])->smartMap(static fn($v) => $v * 2));

        $this->assertInstanceOf(SmartArray::class, $raw);
        $this->assertInstanceOf(SmartArrayHtml::class, $html);
        $this->assertSame([2, 4], $mapped->toArray());

        $this->assertStringContainsString('->asRaw()', $rawMessages[0]);
        $this->assertStringContainsString('->asHtml()', $htmlMessages[0]);
        $this->assertStringContainsString('->map()', $mapMessages[0]);
    }

    /** UPGRADING "Optional renames" - the two with no replacement still work and say so. */
    public function testMethodsWithNoReplacementStillWork(): void
    {
        [$chunked, $chunkMessages] = $this->captureDeprecations(static fn() => SmartArray::new([1, 2, 3])->chunk(2));
        [$isMultiple, $multipleMessages] = $this->captureDeprecations(
            static fn() => SmartArray::new([['a' => 1], ['a' => 2]])->first()->isMultipleOf(1),
        );

        $this->assertSame([[1, 2], [3]], $chunked->toArray());
        $this->assertTrue($isMultiple);
        $this->assertStringContainsString('->chunk() is deprecated', $chunkMessages[0]);
        $this->assertStringContainsString('->isMultipleOf() is deprecated', $multipleMessages[0]);
    }

    /** UPGRADING "Optional renames" - the old class name still resolves. */
    public function testSmartArrayRawClassStillExists(): void
    {
        $this->assertTrue(class_exists('Itools\SmartArray\SmartArrayRaw'));
    }

    //endregion
    //region v2.4.0

    /** UPGRADING "Silent changes" - the mode-switch aliases still work. */
    public function testEnableAndDisableSmartStringsStillSwitchModes(): void
    {
        [$html, $enableMessages]  = $this->captureDeprecations(static fn() => SmartArray::new([['a' => 1]])->enableSmartStrings());
        [$raw, $disableMessages] = $this->captureDeprecations(static fn() => SmartArrayHtml::new([['a' => 1]])->disableSmartStrings());

        $this->assertInstanceOf(SmartArrayHtml::class, $html);
        $this->assertInstanceOf(SmartArray::class, $raw);
        $this->assertStringContainsString('->asHtml()', $enableMessages[0]);
        $this->assertStringContainsString('->asRaw()', $disableMessages[0]);
    }

    //endregion
    //region v2.0.1

    /** UPGRADING "Values are raw by default" - raw echoes unencoded, HTML mode encodes. */
    public function testRawModeEchoesUnencodedAndHtmlModeEncodes(): void
    {
        $name = "Jean O'Brien <script>";

        $this->assertSame($name, (string)SmartArray::new(['name' => $name])->name);
        $this->assertSame('Jean O&apos;Brien &lt;script&gt;', (string)SmartArrayHtml::new(['name' => $name])->name);
    }

    //endregion
}
