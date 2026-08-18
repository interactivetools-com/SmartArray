<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Integration;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use Itools\SmartString\SmartString;

/**
 * Every executable example in README.md and docs/, run as written.
 *
 * The setup and the chain come from the document; the expected values are
 * whatever the library actually produces. If a document's output block ever
 * disagrees with the code, the test pins the ACTUAL output and a "DOCS
 * MISMATCH" comment records the disagreement until the document is fixed.
 *
 * Documented output uses a zero-width space after each "&" so PHPStorm's
 * Markdown preview doesn't decode the entity; expected values here are the
 * real output, without it.
 *
 * Skipped examples, and why:
 * - ZenDB/CMS Builder query examples (DB::select) - no database in this suite
 * - or404(), orDie(), orRedirect() when they fire (they exit or send headers);
 *   the pass-through path, where a record exists, is covered
 * - debug() output formatting - covered by Unit/DebugTest
 * - composer/bash install lines
 */
class DocsExamplesTest extends SmartArrayTestCase
{
    //region README: Before and After

    /** The intro's "you can write code like this" block. */
    public function testReadmeIntroWhereFeaturedChainPrintsEncodedSummaries(): void
    {
        $articles = SmartArrayHtml::new([
            ['title' => 'Fall Fair Sept 20-21', 'featured' => 1, 'summary' => '<p>Join us for the <b>annual fair</b> at Memorial Park.</p>'],
            ['title' => 'Road Closures',        'featured' => 0, 'summary' => '<p>Main St is closed.</p>'],
            ['title' => "Library's New Hours",  'featured' => 1, 'summary' => '<p>Open late Thursdays &amp; Fridays.</p>'],
        ]);

        [, $output] = $this->captureOutput(static function () use ($articles): void {
            foreach ($articles->where('featured') as $article) {
                echo "<h2>$article->title</h2>\n";
                echo "<p>{$article->summary->textOnly()->maxChars(120, '...')}</p>\n";
            }
        });

        $this->assertSame(
            "<h2>Fall Fair Sept 20-21</h2>\n"
            . "<p>Join us for the annual fair at Memorial Park.</p>\n"
            . "<h2>Library&apos;s New Hours</h2>\n"
            . "<p>Open late Thursdays &amp; Fridays.</p>\n",
            $output,
        );
    }

    //endregion
    //region README: You're Never Locked In

    public function testReadmeToArrayAndValueReturnOriginals(): void
    {
        $orders = SmartArrayHtml::new([
            ['id' => 1, 'total' => 24.99],
            ['id' => 2, 'total' => 89.99],
        ]);

        $rows = $orders->toArray();
        $this->assertSame([['id' => 1, 'total' => 24.99], ['id' => 2, 'total' => 89.99]], $rows);

        $order = $orders->first();
        $total = $order->total->value();
        $this->assertSame(24.99, $total);
    }

    //endregion
    //region Getting Started: Your First SmartArray

    /** The three users used throughout getting-started.md. */
    private static function gettingStartedUsers(): array
    {
        return [
            ['name' => "Jean O'Brien",    'city' => 'Vancouver', 'joined' => '2025-11-05'],
            ['name' => 'Tom & Jerry Inc', 'city' => 'Ottawa',    'joined' => '2024-03-14'],
            ['name' => 'Sam Smith',       'city' => 'Calgary',   'joined' => '2026-01-22'],
        ];
    }

    public function testGettingStartedForeachEchoesEncodedFields(): void
    {
        $users = SmartArrayHtml::new(self::gettingStartedUsers());

        [, $output] = $this->captureOutput(static function () use ($users): void {
            foreach ($users as $user) {
                echo "<li>$user->name from $user->city</li>\n";
            }
        });

        $this->assertSame(
            "<li>Jean O&apos;Brien from Vancouver</li>\n"
            . "<li>Tom &amp; Jerry Inc from Ottawa</li>\n"
            . "<li>Sam Smith from Calgary</li>\n",
            $output,
        );
    }

    public function testGettingStartedNestedRowsAreSmartArrays(): void
    {
        $users = SmartArrayHtml::new(self::gettingStartedUsers());

        $this->assertInstanceOf(SmartArrayHtml::class, $users->first());
    }

    public function testGettingStartedFirstLastAndAtReadSingleRows(): void
    {
        $users = SmartArrayHtml::new(self::gettingStartedUsers());

        [, $output] = $this->captureOutput(static function () use ($users): void {
            echo $users->first()->name;
            echo "\n";
            echo $users->last()->city;
            echo "\n";
            echo $users->at(1)->name;
        });

        $this->assertSame("Jean O&apos;Brien\nCalgary\nTom &amp; Jerry Inc", $output);
    }

    public function testGettingStartedSmartStringMethodChainsInsideBraces(): void
    {
        $users = SmartArrayHtml::new(self::gettingStartedUsers());
        $user  = $users->first();

        [, $output] = $this->captureOutput(static function () use ($user): void {
            echo "Joined: {$user->joined->dateFormat('M j, Y')}";
        });

        $this->assertSame('Joined: Nov 5, 2025', $output);
    }

    //endregion
    //region Getting Started: Empty Results and Blank Fields

    public function testGettingStartedIsEmptyGuardAndOrFallback(): void
    {
        $users = SmartArrayHtml::new([
            ['name' => "Jean O'Brien", 'city' => 'Vancouver', 'phone' => '604-555-1234'],
            ['name' => 'Sam Smith',    'city' => 'Calgary',   'phone' => ''],
        ]);

        [, $output] = $this->captureOutput(static function () use ($users): void {
            if ($users->isEmpty()) {
                echo "<p>No users found.</p>";
            }

            foreach ($users as $user) {
                echo "<li>$user->name - {$user->phone->or('(no phone)')}</li>\n";
            }
        });

        $this->assertSame(
            "<li>Jean O&apos;Brien - 604-555-1234</li>\n<li>Sam Smith - (no phone)</li>\n",
            $output
        );
    }

    public function testGettingStartedForeachOverEmptyCollectionRunsZeroTimes(): void
    {
        $none = SmartArrayHtml::new([]);

        $this->assertTrue($none->isEmpty());

        [, $output] = $this->captureOutput(static function () use ($none): void {
            foreach ($none as $user) {
                echo "<li>$user->name</li>\n";
            }
        });

        $this->assertSame('', $output);
    }

    //endregion
    //region Getting Started: The Mental Model

    public function testGettingStartedLogicUsesOriginalValuesAndOutputStillEncodes(): void
    {
        $users = SmartArrayHtml::new(self::gettingStartedUsers());

        $local = $users->where('city', 'Vancouver')->sortBy('name');

        [, $output] = $this->captureOutput(static function () use ($local): void {
            foreach ($local as $user) {
                echo "<li>$user->name</li>\n";
            }
        });

        $this->assertSame("<li>Jean O&apos;Brien</li>\n", $output);
    }

    //endregion
    //region Getting Started: Converting to Plain Arrays

    public function testGettingStartedToArrayRoundTripIsLossless(): void
    {
        $users = SmartArrayHtml::new(self::gettingStartedUsers());

        $this->assertSame(self::gettingStartedUsers(), $users->toArray());
    }

    public function testGettingStartedValueReturnsOriginalOnASingleField(): void
    {
        $user = SmartArrayHtml::new(self::gettingStartedUsers())->first();

        $this->assertSame("Jean O'Brien", $user->name->value());
    }

    //endregion
    //region Displaying Fields: Reading Fields

    /** The two users used through displaying-fields.md. */
    private static function displayingFieldsUsers(): array
    {
        return [
            ['name' => "Jean O'Brien",    'city' => 'Vancouver', 'phone' => '604-555-0132'],
            ['name' => 'Tom & Jerry Inc', 'city' => 'Ottawa',    'phone' => null],
        ];
    }

    public function testDisplayingFieldsArrowOperatorReadsAField(): void
    {
        $user = SmartArrayHtml::new(self::displayingFieldsUsers())->first();

        [, $output] = $this->captureOutput(static fn() => print($user->name));

        $this->assertSame('Jean O&apos;Brien', $output);
    }

    public function testDisplayingFieldsInterpolationMatchesThePlainArrayVersion(): void
    {
        $user  = SmartArrayHtml::new(self::displayingFieldsUsers())->first();
        $plain = $user->toArray();

        [, $plainOutput] = $this->captureOutput(static function () use ($plain): void {
            echo "<p>Contact {$plain['name']} in {$plain['city']} at {$plain['phone']}</p>";
        });
        [, $smartOutput] = $this->captureOutput(static function () use ($user): void {
            echo "<p>Contact $user->name in $user->city at $user->phone</p>";
        });

        // The plain version interpolates the raw value; the SmartArray version encodes it
        $this->assertSame("<p>Contact Jean O'Brien in Vancouver at 604-555-0132</p>", $plainOutput);
        $this->assertSame('<p>Contact Jean O&apos;Brien in Vancouver at 604-555-0132</p>', $smartOutput);
    }

    public function testDisplayingFieldsMethodChainInsideAString(): void
    {
        $user = SmartArrayHtml::new(self::displayingFieldsUsers())->last();

        [, $output] = $this->captureOutput(static function () use ($user): void {
            echo "Phone: {$user->phone->or('(no phone)')}<br>\n";
        });

        $this->assertSame("Phone: (no phone)<br>\n", $output);
    }

    //endregion
    //region Displaying Fields: Fallbacks with or()

    public function testDisplayingFieldsOrShowsDefaultsForBlankAndNull(): void
    {
        $user = SmartArrayHtml::new(['name' => 'Jean', 'phone' => null, 'nickname' => '']);

        [, $output] = $this->captureOutput(static function () use ($user): void {
            echo $user->phone->or('(no phone)');
            echo "\n";
            echo $user->nickname->or('Guest');
            echo "\n";
            echo $user->nickname->or($user->name);
        });

        $this->assertSame("(no phone)\nGuest\nJean", $output);
    }

    //endregion
    //region Displaying Fields: Showing a "No Results" Message

    public function testDisplayingFieldsEmptyBranchReportsCountAndRows(): void
    {
        $users   = SmartArrayHtml::new(self::displayingFieldsUsers());
        $matches = $users->where('city', 'Ottawa');

        [, $output] = $this->captureOutput(static function () use ($matches): void {
            if ($matches->isEmpty()) {
                echo '<p>No users found.</p>';
            } else {
                echo "<p>Found {$matches->count()} user(s):</p>\n";
                foreach ($matches as $user) {
                    echo "<li>$user->name</li>\n";
                }
            }
        });

        $this->assertSame("<p>Found 1 user(s):</p>\n<li>Tom &amp; Jerry Inc</li>\n", $output);
    }

    //endregion
    //region Displaying Fields: When Data Is Missing

    public function testDisplayingFieldsEmptyResultPrintsNothing(): void
    {
        $users = SmartArrayHtml::new([]);  // the doc's empty table

        [$result, $output] = $this->captureOutput(static fn() => print($users->first()->name));

        $this->assertSame('', $output);
        $this->assertSame(1, $result, 'print() returns 1; the point is that nothing was written');
    }

    public function testDisplayingFieldsTypoOnAResultRowWarnsWithCallerFileAndLine(): void
    {
        $users = SmartArrayHtml::new(self::displayingFieldsUsers());
        $user  = $users->first();

        [, $output] = $this->captureOutput(static fn() => $user->nmae);

        $this->assertStringContainsString('Warning: nmae is undefined', $output);
        $this->assertStringContainsString(basename(__FILE__), $output, 'the warning names the caller, not library internals');
    }

    //endregion
    //region Displaying Fields: Requiring Data: The Guards

    /** The guard's pass-through path: a record exists, so it hands the row back and the page continues. */
    public function testDisplayingFieldsOr404ReturnsTheRowWhenTheRecordExists(): void
    {
        $articles = SmartArrayHtml::new([
            ['num' => 7, 'title' => 'Fall Fair Sept 20-21'],
        ]);
        $num = 7;  // the doc reads this from $_GET['num']

        $article = $articles->where('num', $num)->first()->or404('Article not found');

        [, $output] = $this->captureOutput(static function () use ($article): void {
            echo "<h1>$article->title</h1>";
        });

        $this->assertSame('<h1>Fall Fair Sept 20-21</h1>', $output);
    }

    //endregion
    //region Displaying Fields: Checking a Single Field

    public function testDisplayingFieldsIsMissingChecksWithoutStoppingThePage(): void
    {
        $user = SmartArrayHtml::new(self::displayingFieldsUsers())->last();

        [, $output] = $this->captureOutput(static function () use ($user): void {
            if ($user->phone->isMissing()) {
                echo '<p>No phone on file</p>';
            }
        });

        $this->assertSame('<p>No phone on file</p>', $output);
    }

    //endregion
    //region Displaying Fields: Keys Property Syntax Can't Type

    public function testDisplayingFieldsBraceSyntaxReadsKeysPropertySyntaxCannotType(): void
    {
        $row = SmartArrayHtml::new(['users.id' => 42, 'first-name' => 'Jean', 0 => 'zero']);

        [, $output] = $this->captureOutput(static function () use ($row): void {
            echo $row->{'users.id'};
            echo "\n";
            echo $row->{'first-name'};
            echo "\n";
            echo $row->{0};
        });

        $this->assertSame("42\nJean\nzero", $output);
    }

    public function testDisplayingFieldsCollectionsAreWritable(): void
    {
        $user = SmartArrayHtml::new(['name' => 'Jean']);

        $user->status = 'Active';

        $this->assertSame(['name' => 'Jean', 'status' => 'Active'], $user->toArray());
    }

    //endregion
    //region Outputting HTML: How Auto-Encoding Works

    public function testOutputtingHtmlFieldEncodesOnOutputAndValueReturnsTheOriginal(): void
    {
        $article = SmartArrayHtml::new(['title' => 'Tips & Tricks']);

        [, $output] = $this->captureOutput(static function () use ($article): void {
            echo $article->title;
            echo "\n";
            echo $article->title->value();
        });

        $this->assertSame("Tips &amp; Tricks\nTips & Tricks", $output);
    }

    //endregion
    //region Outputting HTML: Trusted HTML: rawHtml()

    public function testOutputtingHtmlRawHtmlSkipsEncoding(): void
    {
        $article = SmartArrayHtml::new(['body' => '<p>Use <b>bold</b> for emphasis.</p>']);

        [, $encoded] = $this->captureOutput(static fn() => print($article->body));
        [, $raw]     = $this->captureOutput(static fn() => print($article->body->rawHtml()));

        $this->assertSame('&lt;p&gt;Use &lt;b&gt;bold&lt;/b&gt; for emphasis.&lt;/p&gt;', $encoded);
        $this->assertSame('<p>Use <b>bold</b> for emphasis.</p>', $raw);
    }

    //endregion
    //region Outputting HTML: Loop Layout

    public function testOutputtingHtmlIsLastPlacesSeparatorsBetweenItems(): void
    {
        $tags = SmartArrayHtml::new([['name' => 'PHP'], ['name' => 'MySQL'], ['name' => 'Tutorials']]);

        [, $output] = $this->captureOutput(static function () use ($tags): void {
            foreach ($tags as $tag) {
                echo $tag->name;
                if (!$tag->isLast()) {
                    echo ', ';
                }
            }
        });

        $this->assertSame('PHP, MySQL, Tutorials', $output);
    }

    public function testOutputtingHtmlPositionSinglesOutTheFirstThreeRows(): void
    {
        $articles = SmartArrayHtml::new([
            ['title' => 'Fall Fair Sept 20-21'],
            ['title' => 'New Trail Maps'],
            ['title' => 'Road Closures on Main St'],
            ['title' => 'Library Summer Hours'],
        ]);

        [, $output] = $this->captureOutput(static function () use ($articles): void {
            foreach ($articles as $article) {
                $class = $article->position() <= 3 ? 'featured' : 'normal';
                echo "<li class='$class'>$article->title</li>\n";
            }
        });

        $this->assertSame(
            "<li class='featured'>Fall Fair Sept 20-21</li>\n"
            . "<li class='featured'>New Trail Maps</li>\n"
            . "<li class='featured'>Road Closures on Main St</li>\n"
            . "<li class='normal'>Library Summer Hours</li>\n",
            $output,
        );
    }

    //endregion
    //region Outputting HTML: Keys Are Never Encoded

    public function testOutputtingHtmlForeachKeysAreRawAndKeysMethodEncodes(): void
    {
        $users = SmartArrayHtml::new([
            ['name' => 'Jean', 'city' => "St. John's"],
            ['name' => 'Tom',  'city' => 'Ottawa'],
        ]);

        $usersByCity = $users->groupBy('city');

        // WRONG - foreach keys are plain values
        [, $wrong] = $this->captureOutput(static function () use ($usersByCity): void {
            foreach ($usersByCity as $city => $_residents) {
                echo "<option>$city</option>\n";
            }
        });

        // RIGHT - keys() hands them back as fields, so they encode
        [, $right] = $this->captureOutput(static function () use ($usersByCity): void {
            foreach ($usersByCity->keys() as $city) {
                echo "<option>$city</option>\n";
            }
        });

        $this->assertSame("<option>St. John's</option>\n<option>Ottawa</option>\n", $wrong);
        $this->assertSame("<option>St. John&apos;s</option>\n<option>Ottawa</option>\n", $right);
    }

    //endregion
    //region Filtering and Sorting: Matching Rows

    /** The three users used through filtering-and-sorting.md. */
    private static function filteringUsers(): array
    {
        return [
            ['name' => 'Jean', 'status' => 'Active',   'role' => 'admin',  'newsletter' => 1],
            ['name' => 'Tom',  'status' => 'Inactive', 'role' => 'admin',  'newsletter' => 0],
            ['name' => 'Sam',  'status' => 'Active',   'role' => 'editor', 'newsletter' => 1],
        ];
    }

    public function testFilteringWhereMatchesAndChains(): void
    {
        $users = SmartArrayHtml::new(self::filteringUsers());

        $active = $users->where('status', 'Active');
        $admins = $users->where('status', 'Active')->where('role', 'admin');

        $this->assertSame(['Jean', 'Sam'], $active->column('name')->toArray());
        $this->assertSame(['Jean'], $admins->column('name')->toArray());
    }

    public function testFilteringWhereComparesLoosely(): void
    {
        $users = SmartArrayHtml::new([['name' => 'Jean', 'newsletter' => 1]]);

        $this->assertSame(['Jean'], $users->where('newsletter', '1')->column('name')->toArray(), "'1' matches 1");
    }

    public function testFilteringSingleArgumentWhereKeepsTruthyFields(): void
    {
        $users = SmartArrayHtml::new(self::filteringUsers());

        $subscribed = $users->where('newsletter');

        $this->assertSame(['Jean', 'Sam'], $subscribed->column('name')->toArray());
    }

    public function testFilteringWhereNotDropsMatchingRows(): void
    {
        $users = SmartArrayHtml::new(self::filteringUsers());

        $nonAdmins = $users->whereNot('role', 'admin');

        $this->assertSame(['Sam'], $nonAdmins->column('name')->toArray());
    }

    public function testFilteringSingleArgumentWhereNotKeepsEmptyFields(): void
    {
        $users = SmartArrayHtml::new(self::filteringUsers());

        $this->assertSame(['Tom'], $users->whereNot('newsletter')->column('name')->toArray());
    }

    //endregion
    //region Filtering and Sorting: whereInList()

    public function testFilteringWhereInListChainsWithTheWhereFamily(): void
    {
        $pages = SmartArrayHtml::new([
            ['num' => 1, 'title' => 'Home',     'hidden' => 0, 'showIn' => "\tmenu\tfooter\t"],
            ['num' => 2, 'title' => 'About',    'hidden' => 0, 'showIn' => "\tmenu\t"],
            ['num' => 3, 'title' => 'Login',    'hidden' => 0, 'showIn' => "\tmenu\t"],
            ['num' => 4, 'title' => 'Old News', 'hidden' => 1, 'showIn' => "\tmenu\t"],
        ]);

        $menuPages = $pages->where('hidden', 0)->whereNot('title', 'Login')->whereInList('showIn', 'menu');

        [, $output] = $this->captureOutput(static function () use ($menuPages): void {
            foreach ($menuPages as $page) {
                echo "<li>$page->title</li>\n";
            }
        });

        $this->assertSame("<li>Home</li>\n<li>About</li>\n", $output);
    }

    //endregion
    //region Filtering and Sorting: Custom Tests: filter()

    public function testFilteringFilterCallbackReceivesPlainValues(): void
    {
        $tables = SmartArrayHtml::new(['cms_accounts', 'wp_posts', 'cms_orders']);

        $ours = $tables->filter(fn($name) => str_starts_with($name, 'cms_'));

        $this->assertSame([0 => 'cms_accounts', 2 => 'cms_orders'], $ours->toArray(), 'kept rows keep their original keys');
    }

    public function testFilteringWithNoCallbackRemovesEmptyValues(): void
    {
        $mixed = SmartArray::new([0, 1, '', 'a', null, '0', false]);

        $this->assertSame([1 => 1, 3 => 'a'], $mixed->filter()->toArray(), 'keys are preserved');
    }

    //endregion
    //region Filtering and Sorting: Sorting

    public function testFilteringSortByOrdersRowsAndLeavesTheOriginalAlone(): void
    {
        $funds = SmartArrayHtml::new([
            ['name' => 'Fund 10'],
            ['name' => 'Fund 2'],
            ['name' => 'Fund 1'],
        ]);

        $textOrder = $funds->sortBy('name');
        $realOrder = $funds->sortBy('name', SORT_NATURAL);

        $this->assertSame(['Fund 1', 'Fund 10', 'Fund 2'], $textOrder->column('name')->toArray());
        $this->assertSame(['Fund 1', 'Fund 2', 'Fund 10'], $realOrder->column('name')->toArray());
        $this->assertSame(['Fund 10', 'Fund 2', 'Fund 1'], $funds->column('name')->toArray(), 'the original keeps query order');
    }

    public function testFilteringSortOrdersAFlatList(): void
    {
        $tags = SmartArrayHtml::new(['PHP', 'MySQL', 'Apache']);

        [, $output] = $this->captureOutput(static fn() => print($tags->sort()->implode(', ')));

        $this->assertSame('Apache, MySQL, PHP', $output);
    }

    //endregion
    //region Filtering and Sorting: Duplicates and Membership

    public function testFilteringUniqueAndContains(): void
    {
        $tags = SmartArrayHtml::new(['PHP', 'MySQL', 'PHP', 'Apache']);

        [, $output] = $this->captureOutput(static function () use ($tags): void {
            echo $tags->unique()->implode(', ');
            echo "\n";
            var_export($tags->contains('MySQL'));
        });

        $this->assertSame("PHP, MySQL, Apache\ntrue", $output);
    }

    //endregion
    //region Transforming and Grouping: column()

    /** The three authors used through transforming-and-grouping.md. */
    private static function transformingAuthors(): array
    {
        return [
            ['author_id' => 7,  'name' => 'Alice Munro',   'city' => 'Wingham'],
            ['author_id' => 12, 'name' => 'Bob Gibson',    'city' => 'Toronto'],
            ['author_id' => 15, 'name' => 'Carol Shields', 'city' => 'Winnipeg'],
        ];
    }

    public function testTransformingColumnPullsOneFieldFromEveryRow(): void
    {
        $authors = SmartArrayHtml::new(self::transformingAuthors());

        [, $output] = $this->captureOutput(static fn() => print($authors->column('name')->implode(', ')));

        $this->assertSame('Alice Munro, Bob Gibson, Carol Shields', $output);
    }

    public function testTransformingTwoArgumentColumnBuildsAKeyedMap(): void
    {
        $authors = SmartArrayHtml::new(self::transformingAuthors());

        $nameById = $authors->column('name', 'author_id');

        $this->assertSame([7 => 'Alice Munro', 12 => 'Bob Gibson', 15 => 'Carol Shields'], $nameById->toArray());

        [, $output] = $this->captureOutput(static fn() => print($nameById->{7}));
        $this->assertSame('Alice Munro', $output);
    }

    //endregion
    //region Transforming and Grouping: indexBy()

    public function testTransformingIndexByKeysWholeRows(): void
    {
        $authors     = SmartArrayHtml::new(self::transformingAuthors());
        $authorsById = $authors->indexBy('author_id');

        [, $output] = $this->captureOutput(static fn() => print($authorsById->{12}->name));

        $this->assertSame('Bob Gibson', $output);
    }

    public function testTransformingFieldsAsLookupKeysUseValue(): void
    {
        $authorsById = SmartArrayHtml::new(self::transformingAuthors())->indexBy('author_id');

        $book     = SmartArrayHtml::new(['title' => 'Runaway', 'author_id' => 7]);
        $authorId = $book->author_id->value();

        [, $output] = $this->captureOutput(static fn() => print($authorsById->{$authorId}->name));

        $this->assertSame('Alice Munro', $output);
    }

    //endregion
    //region Transforming and Grouping: groupBy()

    public function testTransformingGroupByCollectsRowsPerValue(): void
    {
        $articles = SmartArrayHtml::new([
            ['title' => 'Fall Fair Sept 20-21', 'category' => 'Events'],
            ['title' => 'New Trail Maps',       'category' => 'Parks'],
            ['title' => 'Winter Markets',       'category' => 'Events'],
        ]);

        [, $output] = $this->captureOutput(static function () use ($articles): void {
            foreach ($articles->groupBy('category') as $category => $stories) {
                $category = SmartString::new($category);  // foreach keys come back plain; this makes them encode like fields
                echo "<h3>$category</h3>\n";
                foreach ($stories as $story) {
                    echo "   <li>$story->title</li>\n";
                }
            }
        });

        $this->assertSame(
            "<h3>Events</h3>\n"
            . "   <li>Fall Fair Sept 20-21</li>\n"
            . "   <li>Winter Markets</li>\n"
            . "<h3>Parks</h3>\n"
            . "   <li>New Trail Maps</li>\n",
            $output,
        );
    }

    //endregion
    //region Transforming and Grouping: map(), keys(), values()

    public function testTransformingMapRebuildsEachElement(): void
    {
        $authors = SmartArrayHtml::new(self::transformingAuthors());

        $labels = $authors->map(fn($a) => "$a[name] ($a[city])");

        [, $output] = $this->captureOutput(static fn() => print($labels->implode(', ')));

        $this->assertSame('Alice Munro (Wingham), Bob Gibson (Toronto), Carol Shields (Winnipeg)', $output);
    }

    public function testTransformingKeysReturnsTheKeysAsACollection(): void
    {
        $authorsById = SmartArrayHtml::new(self::transformingAuthors())->indexBy('author_id');

        [, $output] = $this->captureOutput(static fn() => print($authorsById->keys()->implode(', ')));

        $this->assertSame('7, 12, 15', $output);
    }

    public function testTransformingValuesRenumbersSoJsonStaysAnArray(): void
    {
        $authors = SmartArrayHtml::new(self::transformingAuthors());

        $filtered = $authors->filter(fn($a) => $a['city'] !== 'Toronto');

        $this->assertSame('{"0":', substr(json_encode($filtered), 0, 5), 'key gaps make JSON an object');
        $this->assertSame('[', substr(json_encode($filtered->values()), 0, 1), 'values() renumbers, so JSON is an array');
    }

    //endregion
    //region Without SmartStrings: Creating Raw Collections

    /** The two products used through without-smartstrings.md. */
    private static function rawProducts(): array
    {
        return [
            ['sku' => 'A100', 'title' => 'Widget & Sons Kit', 'price' => 24.99, 'salePrice' => 19.99],
            ['sku' => 'B200', 'title' => 'Gadget Pro',        'price' => 89.99, 'salePrice' => null],
        ];
    }

    public function testWithoutSmartStringsFieldsArePlainValues(): void
    {
        $products = SmartArray::new(self::rawProducts());

        [, $output] = $this->captureOutput(static fn() => print($products->first()->title));
        $skuList = $products->column('sku')->implode(', ');
        $price   = $products->first()->price;

        $this->assertSame('Widget & Sons Kit', $output, 'raw mode does not encode');
        $this->assertSame('A100, B200', $skuList);
        $this->assertIsString($skuList, 'implode() returns a plain string in raw mode');
        $this->assertSame(24.99, $price, 'fields keep their original type');
    }

    //endregion
    //region Without SmartStrings: Fallbacks with ??

    public function testWithoutSmartStringsNullCoalescingFallsBackOnNullAndMissing(): void
    {
        $products = SmartArray::new(self::rawProducts());

        $prices = [];
        foreach ($products as $product) {
            $prices[] = $product->salePrice ?? $product->price;
        }

        $this->assertSame([19.99, 89.99], $prices);
    }

    public function testWithoutSmartStringsNullCoalescingDoesNotFireOnStoredEmptyString(): void
    {
        $product = SmartArray::new(['salePrice' => '', 'price' => 89.99]);

        $this->assertSame('', $product->salePrice ?? $product->price, '"" is a stored value, so ?? keeps it');
    }

    //endregion
    //region Without SmartStrings: Getting Data Out

    public function testWithoutSmartStringsJsonEncodeSerializesOriginalValues(): void
    {
        $products = SmartArray::new(self::rawProducts());

        $this->assertSame('["A100","B200"]', json_encode($products->column('sku')));
    }

    public function testWithoutSmartStringsToArrayReturnsPlainNestedArrays(): void
    {
        $products = SmartArray::new(self::rawProducts());

        $this->assertSame(self::rawProducts(), $products->toArray());
    }

    //endregion
    //region Without SmartStrings: Converting Between Modes

    public function testWithoutSmartStringsAsHtmlSwitchesModesForOutput(): void
    {
        $products = SmartArray::new(self::rawProducts());

        [, $output] = $this->captureOutput(static function () use ($products): void {
            foreach ($products->asHtml() as $product) {
                echo "<h3>$product->title</h3>\n";
            }
        });

        $this->assertSame("<h3>Widget &amp; Sons Kit</h3>\n<h3>Gadget Pro</h3>\n", $output);
        $this->assertInstanceOf(SmartArray::class, $products, 'the original is unchanged');
    }

    public function testWithoutSmartStringsSameModeReturnsTheSameObject(): void
    {
        $products = SmartArray::new(self::rawProducts());

        $this->assertSame($products, $products->asRaw());
    }

    //endregion
    //region Without SmartStrings: Type Hints That Accept Both Modes

    public function testWithoutSmartStringsHtmlModeIsNotASubclassOfSmartArray(): void
    {
        $html = SmartArrayHtml::new(self::rawProducts());

        $this->assertNotInstanceOf(SmartArray::class, $html);
        $this->assertInstanceOf(SmartArrayBase::class, $html);
    }

    public function testWithoutSmartStringsSmartArrayBaseHintAcceptsBothModes(): void
    {
        $countActive = static function (SmartArrayBase $rows): int {
            return $rows->where('status', 'Active')->count();
        };

        $records = [['status' => 'Active'], ['status' => 'Inactive'], ['status' => 'Active']];

        $this->assertSame(2, $countActive(SmartArray::new($records)));
        $this->assertSame(2, $countActive(SmartArrayHtml::new($records)));
    }

    //endregion
    //region Common Patterns: Showing Related Names Without a Join

    public function testCommonPatternsKeyedMapReplacesAJoin(): void
    {
        $authors = SmartArrayHtml::new([
            ['author_id' => 7,  'name' => 'Alice Munro'],
            ['author_id' => 12, 'name' => 'Bob Gibson'],
        ]);
        $articles = SmartArrayHtml::new([
            ['title' => 'Runaway',      'author_id' => 7],
            ['title' => 'Local Trails', 'author_id' => 12],
        ]);

        $authorById = $authors->column('name', 'author_id');

        [, $output] = $this->captureOutput(static function () use ($articles, $authorById): void {
            foreach ($articles as $article) {
                $authorId   = $article->author_id->value();
                $authorName = $authorById->{$authorId}->or('Unknown Author');
                echo "<li>$article->title by $authorName</li>\n";
            }
        });

        $this->assertSame("<li>Runaway by Alice Munro</li>\n<li>Local Trails by Bob Gibson</li>\n", $output);
    }

    public function testCommonPatternsKeyedMapMissLandsOnTheFallbackSilently(): void
    {
        $authorById = SmartArrayHtml::new([['author_id' => 7, 'name' => 'Alice Munro']])->column('name', 'author_id');

        [, $output] = $this->captureOutput(static fn() => print($authorById->{999}->or('Unknown Author')));

        $this->assertSame('Unknown Author', $output, 'derived maps do not warn on a miss');
    }

    //endregion
    //region Common Patterns: Select Dropdowns from Query Results

    public function testCommonPatternsSelectDropdownMarksTheCurrentSelection(): void
    {
        $authors = SmartArrayHtml::new([
            ['author_id' => 7,  'name' => 'Alice Munro'],
            ['author_id' => 12, 'name' => 'Bob Gibson'],
        ]);
        $selectedId = 12;  // the doc reads this from $_GET['author']

        [, $output] = $this->captureOutput(static function () use ($authors, $selectedId): void {
            echo "<select name='author'>\n";
            foreach ($authors as $author) {
                $selected = $author->author_id->value() == $selectedId ? ' selected' : '';
                echo "<option value='$author->author_id'$selected>$author->name</option>\n";
            }
            echo "</select>\n";
        });

        $this->assertSame(
            "<select name='author'>\n"
            . "<option value='7'>Alice Munro</option>\n"
            . "<option value='12' selected>Bob Gibson</option>\n"
            . "</select>\n",
            $output,
        );
    }

    //endregion
    //region Common Patterns: Top N with a "More" Link

    public function testCommonPatternsTopNBreaksAfterThreeRows(): void
    {
        $articles = SmartArrayHtml::new([
            ['title' => 'Runaway'], ['title' => 'Local Trails'], ['title' => 'Harbour Lights'],
            ['title' => 'Night Ferry'], ['title' => 'Winter Roads'],
        ]);

        [, $output] = $this->captureOutput(static function () use ($articles): void {
            foreach ($articles as $article) {
                if ($article->position() > 3) {
                    echo "<li><a href='articles.php'>more...</a></li>\n";
                    break;
                }
                echo "<li>$article->title</li>\n";
            }
        });

        $this->assertSame(
            "<li>Runaway</li>\n<li>Local Trails</li>\n<li>Harbour Lights</li>\n<li><a href='articles.php'>more...</a></li>\n",
            $output,
        );
    }

    //endregion
    //region Common Patterns: Grouped Headings with Counts

    public function testCommonPatternsGroupedHeadingsShowCounts(): void
    {
        $listings = SmartArrayHtml::new([
            ['title' => 'Fall Fair Sept 20-21', 'category' => 'Events'],
            ['title' => 'New Trail Maps',       'category' => 'Parks'],
            ['title' => 'Winter Markets',       'category' => 'Events'],
        ]);

        [, $output] = $this->captureOutput(static function () use ($listings): void {
            foreach ($listings->groupBy('category') as $category => $rows) {
                $category = SmartString::new($category);  // foreach keys come back plain; this makes them encode like fields
                echo "<h3>$category ({$rows->count()})</h3>\n";
                foreach ($rows as $row) {
                    echo "   <li>$row->title</li>\n";
                }
            }
        });

        $this->assertSame(
            "<h3>Events (2)</h3>\n"
            . "   <li>Fall Fair Sept 20-21</li>\n"
            . "   <li>Winter Markets</li>\n"
            . "<h3>Parks (1)</h3>\n"
            . "   <li>New Trail Maps</li>\n",
            $output,
        );
    }

    //endregion
    //region Common Patterns: Safe Id Lists for SQL IN Clauses

    public function testCommonPatternsIdListForInClause(): void
    {
        $trackers = SmartArrayHtml::new([['id' => '5'], ['id' => '12'], ['id' => '9']]);

        $ids = $trackers->column('id')->map('intval')->implode(',')->or('0')->string();

        $this->assertSame('5,12,9', $ids);
        $this->assertSame("SELECT * FROM `logs` WHERE `tracker_id` IN (5,12,9)", "SELECT * FROM `logs` WHERE `tracker_id` IN ($ids)");
    }

    public function testCommonPatternsIdListFallsBackToZeroWhenNothingMatched(): void
    {
        $trackers = SmartArrayHtml::new([]);

        $ids = $trackers->column('id')->map('intval')->implode(',')->or('0')->string();

        $this->assertSame('0', $ids, 'an empty IN clause is a SQL syntax error');
    }

    //endregion
    //region Common Patterns: Results with Unpredictable Column Names

    public function testCommonPatternsColumnAtReadsByPosition(): void
    {
        $result = SmartArrayHtml::new([
            ['Tables_in_shop' => 'orders'],
            ['Tables_in_shop' => 'accounts'],
            ['Tables_in_shop' => 'users'],
        ]);

        [, $output] = $this->captureOutput(static fn() => print($result->columnAt(0)->sort()->implode(', ')));

        $this->assertSame('accounts, orders, users', $output);
    }

    //endregion
    //region Method Reference: Basic Usage

    public function testMethodReferenceBasicUsageReadsFieldsAndChains(): void
    {
        $records = [
            ['name' => 'Sam',  'status' => 'Active'],
            ['name' => 'Jean', 'status' => 'Active'],
            ['name' => 'Tom',  'status' => 'Inactive'],
        ];

        $users = SmartArrayHtml::new($records);
        $data  = SmartArray::new($records);

        [, $output] = $this->captureOutput(static fn() => print($users->first()->name));
        $active = $users->where('status', 'Active')->sortBy('name');

        $this->assertSame('Sam', $output);
        $this->assertSame(['Jean', 'Sam'], $active->column('name')->toArray());
        $this->assertSame('Sam', $data->first()->name, 'raw mode returns plain values');
    }

    public function testMethodReferenceGetRawValueUnwrapsAnythingYouHandIt(): void
    {
        $field = SmartArrayHtml::new(['name' => 'Jean'])->name;

        $this->assertSame('Jean', SmartArray::getRawValue($field));
        $this->assertSame('plain', SmartArray::getRawValue('plain'));
    }

    /** The Writing Values block: assignment, nested arrays, unset. */
    public function testMethodReferenceWritingValuesStoresFieldsRowsAndRemovesKeys(): void
    {
        $user = SmartArrayHtml::new(['status' => 'Pending', 'nickname' => 'Jay']);

        $user->status = 'Active';
        $user->tags   = ['staff', 'admin'];
        unset($user->nickname);

        $this->assertSame('Active', (string)$user->status);
        $this->assertInstanceOf(SmartArrayHtml::class, $user->tags);
        $this->assertSame(['staff', 'admin'], $user->tags->toArray());
        $this->assertSame(['status' => 'Active', 'tags' => ['staff', 'admin']], $user->toArray());
    }

    //endregion
    //region Troubleshooting: empty() and if() checks

    public function testTroubleshootingEmptyChecksAnswerByObjectRules(): void
    {
        $user = SmartArrayHtml::new([['name' => 'Jean', 'nickname' => '', 'logins' => 0, 'phone' => null]])->first();

        $this->assertTrue(empty($user->phone), 'stored NULL reads as missing');
        $this->assertFalse(empty($user->nickname), '"" is stored, so the check sees an object');
        $this->assertFalse(empty($user->logins), 'same for 0');
    }

    public function testTroubleshootingEmptyOnAMissingKeyIsTrueAndSilent(): void
    {
        $user = SmartArrayHtml::new([['name' => 'Jean']])->first();

        // empty() asks __isset() first and only reads the value if that says yes,
        // so the missing-key warning never fires here
        [$isEmpty, $output] = $this->captureOutput(static fn() => empty($user->missing));

        $this->assertTrue($isEmpty, 'keys that do not exist read as empty');
        $this->assertSame('', $output);
    }

    public function testTroubleshootingAskTheFieldInstead(): void
    {
        $user = SmartArrayHtml::new([['name' => 'Jean', 'nickname' => '', 'logins' => 0, 'phone' => null]])->first();

        $this->assertTrue($user->nickname->isEmpty(), '"", NULL, 0, and "0" all count as empty');
        $this->assertTrue($user->phone->isMissing(), 'NULL or ""');
        $this->assertFalse($user->logins->isMissing(), 'zero counts as present');
    }

    //endregion
    //region Troubleshooting: A === null check never matches

    public function testTroubleshootingIdentityChecksAgainstNull(): void
    {
        $user = SmartArrayHtml::new([['name' => 'Jean', 'phone' => null]])->first();

        $this->assertFalse($user->phone === null, 'the field is an object');
        $this->assertTrue($user->phone->value() === null, 'the original value is null');
        $this->assertTrue($user->phone->isMissing());
    }

    public function testTroubleshootingRawModeReturnsRealNull(): void
    {
        $user = SmartArray::new([['name' => 'Jean', 'phone' => null]])->first();

        $this->assertTrue($user->phone === null, 'raw mode hands back plain PHP types');
    }

    //endregion
    //region Troubleshooting: Can't convert SmartArrayHtml to string

    public function testTroubleshootingEchoingACollectionWarns(): void
    {
        $cities = SmartArrayHtml::new(['Vancouver', 'Ottawa']);

        [, $wrong] = $this->captureOutput(static function () use ($cities): void {
            echo "Cities: $cities";
        });
        [, $right] = $this->captureOutput(static function () use ($cities): void {
            echo "Cities: {$cities->implode(', ')}";
        });

        $this->assertStringContainsString("Can't convert SmartArrayHtml to string", $wrong);
        $this->assertStringContainsString('See SmartArray docs for more info', $wrong, 'no URL in spontaneous output');
        $this->assertSame('Cities: Vancouver, Ottawa', $right);
    }

    //endregion
    //region Troubleshooting: some_field is undefined

    public function testTroubleshootingNullCoalescingReadsAMaybeMissingKeyWithoutWarning(): void
    {
        $row = SmartArrayHtml::new([['title' => 'Fall Fair']])->first();

        [$value, $output] = $this->captureOutput(static fn() => $row->some_field ?? '');

        $this->assertSame('', $value);
        $this->assertSame('', $output, '?? never warns');
    }

    public function testTroubleshootingOnlyResultRowsWarn(): void
    {
        $standalone = SmartArrayHtml::new(['title' => 'Fall Fair']);
        $keyedMap   = SmartArrayHtml::new([['id' => 7, 'name' => 'Alice']])->indexBy('id');

        [, $standaloneOutput] = $this->captureOutput(static fn() => $standalone->missing);
        [, $mapOutput]        = $this->captureOutput(static fn() => $keyedMap->{999});

        $this->assertSame('', $standaloneOutput, 'standalone arrays render blank silently');
        $this->assertSame('', $mapOutput, 'a miss on a keyed map is a normal no-match');
    }

    //endregion
    //region Troubleshooting: Casting with (array)

    public function testTroubleshootingToArrayInsteadOfArrayCast(): void
    {
        $cities = SmartArrayHtml::new(['Vancouver', 'Ottawa']);

        $this->assertNotSame(['Vancouver', 'Ottawa'], (array)$cities, 'the cast exposes internal properties');
        $this->assertSame(['Vancouver', 'Ottawa'], $cities->toArray());
        $this->assertSame(['Vancouver', 'Ottawa'], array_map(SmartArray::getRawValue(...), [...$cities]), 'spread works for flat lists');
    }

    //endregion
    //region Troubleshooting: json_encode() returns an object

    public function testTroubleshootingValuesRenumbersForJson(): void
    {
        $cities = SmartArrayHtml::new(['Vancouver', 'Ottawa']);

        $this->assertSame('{"1":"Ottawa"}', json_encode($cities->filter(fn($c) => $c !== 'Vancouver')));
        $this->assertSame('["Ottawa"]', json_encode($cities->filter(fn($c) => $c !== 'Vancouver')->values()));
    }

    //endregion
    //region Troubleshooting: A lookup using a field as the key renders blank

    public function testTroubleshootingTextKeyLookupNeedsValue(): void
    {
        $facts = SmartArrayHtml::new(["St. John's" => 'Oldest city in North America']);
        $city  = SmartArrayHtml::new(['city' => "St. John's"])->city;

        [, $wrong] = $this->captureOutput(static fn() => print($facts->{$city}));
        [, $right] = $this->captureOutput(static fn() => print($facts->{$city->value()}));

        $this->assertSame('', $wrong, 'the encoded key does not match, so it renders blank');
        $this->assertSame('Oldest city in North America', $right);
    }

    public function testTroubleshootingNumericIdLookupsWorkEitherWay(): void
    {
        $authorsById = SmartArrayHtml::new([['id' => 7, 'name' => 'Alice Munro']])->indexBy('id');
        $authorId    = SmartArrayHtml::new(['author_id' => 7])->author_id;

        $this->assertSame('Alice Munro', $authorsById->{$authorId}->name->value(), 'digits encode to themselves');
        $this->assertSame('Alice Munro', $authorsById->{$authorId->value()}->name->value());
    }

    //endregion
}
