<?php
declare(strict_types=1);

namespace Itools\SmartArray\Tests\Integration;

use Itools\SmartArray\CallerException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use Itools\SmartArray\Tests\Support\SmartArrayTestCase;
use Itools\SmartString\SmartString;
use RuntimeException;

/**
 * Every executable example in README.md and src/help.txt, run as written.
 *
 * The setup and the chain come from the document; the expected values are
 * whatever the library actually produces. Where the document shows an output
 * block that disagrees with the code, the test pins the ACTUAL output and a
 * "DOCS MISMATCH" comment records the disagreement (see the final report for
 * the full list).
 *
 * Skipped examples, and why:
 * - README "Common use cases" fragments under at() ($results, $activities,
 *   $log are never defined; the lines illustrate syntax only)
 * - help.txt SmartArray::new(DB::select('users')) (needs a database)
 * - or404(), orDie() (both exit the process), orRedirect() (sends headers)
 * - Method Reference / help.txt rows that name a method without an example
 *   are covered by the region matching their doc section, not one per row
 */
class DocsExamplesTest extends SmartArrayTestCase
{
    //region README: Quick Start

    /** The three records used by the Quick Start and the isFirst()/isLast() grid example. */
    private static function quickStartRecords(): array
    {
        return [
            ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
            ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
            ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
        ];
    }

    public function testReadmeQuickStartSortByNameOrdersRowsAlphabetically(): void
    {
        $users = SmartArray::new(self::quickStartRecords())
            ->asHtml()
            ->sortBy('name');

        $this->assertInstanceOf(SmartArrayHtml::class, $users);
        $this->assertSame([
            ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
            ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
            ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
        ], $users->toArray());
    }

    public function testReadmeQuickStartForeachEchoesEncodedValues(): void
    {
        $users = SmartArray::new(self::quickStartRecords())->asHtml()->sortBy('name');

        [, $output] = $this->captureOutput(static function () use ($users): void {
            foreach ($users as $user) {
                echo "Name: $user->name, ";
                echo "City: $user->city\n";
            }
        });

        $this->assertSame(
            "Name: John O&apos;Connor, City: New York\n"
            . "Name: Tom &amp; Jerry, City: Vancouver\n"
            . "Name: Xena &quot;X&quot; Smith, City: Los Angeles\n",
            $output,
        );
    }

    public function testReadmeQuickStartFirstNameEncodesApostrophe(): void
    {
        $users = SmartArray::new(self::quickStartRecords())->asHtml()->sortBy('name');

        [, $output] = $this->captureOutput(static function () use ($users): void {
            echo $users->first()->name;
        });

        $this->assertSame('John O&apos;Connor', $output);
    }

    public function testReadmeQuickStartColumnImplodeBuildsIdCsv(): void
    {
        $users = SmartArray::new(self::quickStartRecords())->asHtml()->sortBy('name');

        $userIdAsCSV = $users->column('id')->implode(', ');

        $this->assertInstanceOf(SmartString::class, $userIdAsCSV, 'implode() on SmartArrayHtml returns a SmartString');
        $this->assertSame('10, 20, 15', $userIdAsCSV->value());
    }

    public function testReadmeQuickStartValueReturnsRawInteger(): void
    {
        $users = SmartArray::new(self::quickStartRecords())->asHtml()->sortBy('name');

        $userId = $users->first()->id->value();

        $this->assertSame(10, $userId);
    }

    public function testReadmeQuickStartMissingKeyReturnsSmartNullAndWarns(): void
    {
        // The Quick Start promises a SmartNull instead of an error; it does not
        // mention that reading a missing key also echoes a warning
        $users = SmartArray::new(self::quickStartRecords())->asHtml();

        [$missing, $output] = $this->captureOutput(static fn() => $users->first()->nickname);

        $this->assertInstanceOf(SmartNull::class, $missing);
        $this->assertNull($missing->value());
        $this->assertStringContainsString('Warning: nickname is undefined', $output);
    }

    //endregion
    //region README: Highlighting Recent Articles with position()

    public function testReadmePositionExampleHeadlinesFirstThreeArticles(): void
    {
        $articles = [
            ['title' => 'Astronomers Photograph Distant Galaxy for First Time'],
            ['title' => 'New Species of Butterfly Found in Amazon Rainforest'],
            ['title' => 'Ocean Expedition Maps Unexplored Deep Sea Valleys'],
            ['title' => 'Ancient Star Charts Discovered in Mountain Cave'],
            ['title' => 'Rare Rainbow Clouds Spotted in Nordic Skies'],
            ['title' => 'Desert Expedition Reveals Hidden Oasis Ecosystem'],
            ['title' => 'Mountain Observatory Captures Meteor Shower Images'],
        ];

        $news = SmartArray::new($articles)->asHtml();

        [, $output] = $this->captureOutput(static function () use ($news): void {
            echo "<div class='news-list'>\n";
            foreach ($news as $article) {
                if ($article->position() <= 3) {
                    echo "<h1>$article->title</h1>\n";
                } else {
                    echo "$article->title<br>\n";
                }
            }
            echo "</div>\n";
        });

        $this->assertSame(
            "<div class='news-list'>\n"
            . "<h1>Astronomers Photograph Distant Galaxy for First Time</h1>\n"
            . "<h1>New Species of Butterfly Found in Amazon Rainforest</h1>\n"
            . "<h1>Ocean Expedition Maps Unexplored Deep Sea Valleys</h1>\n"
            . "Ancient Star Charts Discovered in Mountain Cave<br>\n"
            . "Rare Rainbow Clouds Spotted in Nordic Skies<br>\n"
            . "Desert Expedition Reveals Hidden Oasis Ecosystem<br>\n"
            . "Mountain Observatory Captures Meteor Shower Images<br>\n"
            . "</div>\n",
            $output,
        );
    }

    //endregion
    //region README: Accessing Elements by Position with at()

    public function testReadmeAtExampleReadsPositionsFromBothEnds(): void
    {
        $topSellers = [
            ['rank' => 1, 'title' => 'The Great Gatsby',      'sales' => 25000000],
            ['rank' => 2, 'title' => '1984',                  'sales' => 20000000],
            ['rank' => 3, 'title' => 'To Kill a Mockingbird', 'sales' => 18000000],
            ['rank' => 4, 'title' => 'The Catcher in the Rye', 'sales' => 15000000],
            ['rank' => 5, 'title' => 'The Hobbit',            'sales' => 14000000],
        ];

        $books = SmartArray::new($topSellers)->asHtml();

        [, $output] = $this->captureOutput(static function () use ($books): void {
            echo $books->at(0)->title . "|";
            echo $books->at(2)->title . "|";
            echo $books->at(-1)->title . "|";
            echo $books->at(-2)->title;
        });

        $this->assertSame('The Great Gatsby|To Kill a Mockingbird|The Hobbit|The Catcher in the Rye', $output);
    }

    //endregion
    //region README: Looking Up Authors by ID with indexBy()

    /** The author lookup used by the indexBy() example. */
    private static function authorsById(): SmartArrayHtml
    {
        $authors = [
            ['author_id' => 101, 'name' => 'Jane Austen',     'genre' => 'Literary Fiction'],
            ['author_id' => 102, 'name' => 'George Orwell',   'genre' => 'Political Fiction'],
            ['author_id' => 103, 'name' => 'Isaac Asimov',    'genre' => 'Science Fiction'],
            ['author_id' => 104, 'name' => 'Agatha Christie', 'genre' => 'Mystery'],
        ];

        return SmartArray::new($authors)->indexBy('author_id')->asHtml();
    }

    /** The three articles joined against the author lookup. */
    private static function joinArticles(): array
    {
        return [
            ['article_id' => 1, 'title' => 'Pride and Programming', 'author_id' => 101],
            ['article_id' => 2, 'title' => 'Digital Dystopia',      'author_id' => 102],
            ['article_id' => 3, 'title' => 'Robot Psychology',      'author_id' => 103],
        ];
    }

    public function testReadmeIndexByExampleLooksUpAuthorsByIntegerKey(): void
    {
        $authorById = self::authorsById();

        [, $output] = $this->captureOutput(static function () use ($authorById): void {
            echo $authorById->get(101)->name;
            echo "|";
            echo $authorById->get(103)->genre;
        });

        $this->assertSame('Jane Austen|Science Fiction', $output);
        $this->assertSame([101, 102, 103, 104], $authorById->keys()->toArray(), 'integer field values stay integer keys');
    }

    // The join loop as documented: get() unwraps the SmartString key, so the
    // example runs as written in README.md lines 193-212
    public function testReadmeIndexByJoinExampleOutput(): void
    {
        $authorById = self::authorsById();
        $articles   = SmartArray::new(self::joinArticles())->asHtml();

        [, $output] = $this->captureOutput(static function () use ($articles, $authorById): void {
            foreach ($articles as $article) {
                $author = $authorById->get($article->author_id);
                echo "Title: $article->title\n";
                echo "By: $author->name ($author->genre)\n\n";
            }
        });

        $this->assertSame(
            "Title: Pride and Programming\n"
            . "By: Jane Austen (Literary Fiction)\n\n"
            . "Title: Digital Dystopia\n"
            . "By: George Orwell (Political Fiction)\n\n"
            . "Title: Robot Psychology\n"
            . "By: Isaac Asimov (Science Fiction)\n\n",
            $output,
        );
    }

    /** "If multiple records have the same key value, later records will overwrite earlier ones" */
    public function testReadmeIndexByNoteDuplicateKeysKeepTheLastRow(): void
    {
        $rows = SmartArray::new([
            ['k' => 'a', 'v' => 1],
            ['k' => 'a', 'v' => 2],
        ]);

        $this->assertSame(['a' => ['k' => 'a', 'v' => 2]], $rows->indexBy('k')->toArray());
    }

    /** "Rows with a null or missing key value are indexed under ''" */
    public function testReadmeIndexByNoteNullAndMissingKeysIndexUnderEmptyString(): void
    {
        $rows = SmartArray::new([
            ['k' => null, 'v' => 1],
            ['v' => 2],
            ['k' => 'x', 'v' => 3],
        ]);

        [$result, $output] = $this->captureOutput(static fn() => $rows->indexBy('k'));

        $this->assertSame([
            ''  => ['v' => 2],                 // the missing-key row overwrote the null-key row
            'x' => ['k' => 'x', 'v' => 3],
        ], $result->toArray());
        $this->assertSame('', $output, 'no warning: the first row has the field');
    }

    //endregion
    //region README: Organizing Books by Genre with groupBy()

    /** The six books used by both groupBy() examples. */
    private static function books(): array
    {
        return [
            ['title' => 'Pride and Prejudice', 'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1813],
            ['title' => '1984',                'author' => 'George Orwell', 'genre' => 'Science Fiction',  'year' => 1949],
            ['title' => 'Foundation',          'author' => 'Isaac Asimov',  'genre' => 'Science Fiction',  'year' => 1951],
            ['title' => 'Emma',                'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1815],
            ['title' => 'I, Robot',            'author' => 'Isaac Asimov',  'genre' => 'Science Fiction',  'year' => 1950],
            ['title' => 'Persuasion',          'author' => 'Jane Austen',   'genre' => 'Literary Fiction', 'year' => 1818],
        ];
    }

    // DOCS MISMATCH: doc's underline is 18 dashes under "Literary Fiction Books:" and 19
    // under "Science Fiction Books:", actual is 23 and 22 (str_repeat('-', strlen($genre) + 7))
    // (README.md lines 267 and 273)
    public function testReadmeGroupByGenreExampleOutput(): void
    {
        $booksByGenre = SmartArray::new(self::books())->groupBy('genre')->asHtml();

        [, $output] = $this->captureOutput(static function () use ($booksByGenre): void {
            foreach ($booksByGenre as $genre => $relatedBooks) {
                echo "\n$genre Books:\n";
                echo str_repeat('-', strlen($genre) + 7) . "\n";

                foreach ($relatedBooks as $book) {
                    echo "- $book->title ($book->year)\n";
                }
            }
        });

        $this->assertSame(
            "\nLiterary Fiction Books:\n"
            . "-----------------------\n"
            . "- Pride and Prejudice (1813)\n"
            . "- Emma (1815)\n"
            . "- Persuasion (1818)\n"
            . "\nScience Fiction Books:\n"
            . "----------------------\n"
            . "- 1984 (1949)\n"
            . "- Foundation (1951)\n"
            . "- I, Robot (1950)\n",
            $output,
        );
    }

    // DOCS MISMATCH: doc's output block orders the author groups Jane Austen, Isaac Asimov,
    // George Orwell; actual order is first appearance (Jane Austen, George Orwell, Isaac
    // Asimov), which is what the "Groups are created in order of first appearance" note
    // promises (README.md lines 278-288)
    public function testReadmeGroupByAuthorExampleOutput(): void
    {
        $booksByAuthor = SmartArray::new(self::books())->groupBy('author')->asHtml();

        [, $output] = $this->captureOutput(static function () use ($booksByAuthor): void {
            foreach ($booksByAuthor as $author => $authorBooks) {
                $years = $authorBooks->column('year')->values()->sort();
                echo "\n$author published {$authorBooks->count()} books ({$years->first()}-{$years->last()}):\n";

                foreach ($authorBooks->sortBy('year') as $book) {
                    echo "- $book->title ($book->year)\n";
                }
            }
        });

        $this->assertSame(
            "\nJane Austen published 3 books (1813-1818):\n"
            . "- Pride and Prejudice (1813)\n"
            . "- Emma (1815)\n"
            . "- Persuasion (1818)\n"
            . "\nGeorge Orwell published 1 books (1949-1949):\n"
            . "- 1984 (1949)\n"
            . "\nIsaac Asimov published 2 books (1950-1951):\n"
            . "- I, Robot (1950)\n"
            . "- Foundation (1951)\n",
            $output,
        );
    }

    /** "Rows with a null or missing group value are grouped under ''... no rows are dropped" */
    public function testReadmeGroupByNoteNullAndMissingValuesGroupUnderEmptyString(): void
    {
        $rows = SmartArray::new([
            ['g' => null, 'v' => 1],
            ['v' => 2],
            ['g' => 'x', 'v' => 3],
        ]);

        [$result, $output] = $this->captureOutput(static fn() => $rows->groupBy('g'));

        $this->assertSame([
            ''  => [['g' => null, 'v' => 1], ['v' => 2]],
            'x' => [['g' => 'x', 'v' => 3]],
        ], $result->toArray());
        $this->assertSame('', $output, 'no warning: the first row has the field');
    }

    //endregion
    //region README: Extracting Unique Tags with column(), unique(), and implode()

    /** The five tagged articles used by the unique-tags pipeline. */
    private static function taggedArticles(): array
    {
        return [
            ['title' => 'Getting Started with PHP', 'tag' => 'PHP'],
            ['title' => 'Understanding Unit Tests', 'tag' => 'Testing'],
            ['title' => 'Data Handling Techniques', 'tag' => 'PHP'],
            ['title' => 'MySQL Best Practices',     'tag' => 'Databases'],
            ['title' => 'Advanced PHP Techniques',  'tag' => 'PHP'],
        ];
    }

    public function testReadmeUniqueTagsPipelineJoinsSortedTags(): void
    {
        $tagList = SmartArray::new(self::taggedArticles())->column('tag')->unique()->sort()->implode(', ');

        $this->assertSame('Databases, PHP, Testing', $tagList);

        [, $output] = $this->captureOutput(static function () use ($tagList): void {
            echo "Topics: $tagList";
        });

        $this->assertSame('Topics: Databases, PHP, Testing', $output);
    }

    public function testReadmeUniqueTagsPipelineStepResults(): void
    {
        // The inline comments show each step as a value list; unique() keeps the
        // original keys, so key 2 is gone and key 3 survives until sort() reindexes
        $articles = SmartArray::new(self::taggedArticles());

        $this->assertSame(['PHP', 'Testing', 'PHP', 'Databases', 'PHP'], $articles->column('tag')->toArray());
        $this->assertSame([0 => 'PHP', 1 => 'Testing', 3 => 'Databases'], $articles->column('tag')->unique()->toArray());
        $this->assertSame(['Databases', 'PHP', 'Testing'], $articles->column('tag')->unique()->sort()->toArray());
    }

    //endregion
    //region README: Building Dynamic HTML Tables

    // The doc's output block is a full <table> rendered from an inline PHP template;
    // the assertions cover the generated rows, not the template's static indentation
    public function testReadmeHtmlTableEncodesHeaderKeysAndCellValues(): void
    {
        $rows = SmartArray::new([
            ['name' => "John O'Connor", 'city' => 'New York',    'status' => 'Active'],
            ['name' => 'Jane <script>', 'city' => 'Los Angeles', 'status' => 'Pending'],
            ['name' => 'Tom & Jerry',   'city' => 'Vancouver',   'status' => 'Active'],
        ])->asHtml();

        [, $output] = $this->captureOutput(static function () use ($rows): void {
            if ($rows->isNotEmpty()) {
                echo '<tr>';
                foreach ($rows->first()->keys() as $field) {
                    echo "<th>$field</th>";
                }
                echo "</tr>\n";
            }
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $value) {
                    echo "<td>$value</td>";
                }
                echo "</tr>\n";
            }
        });

        $this->assertSame(
            "<tr><th>name</th><th>city</th><th>status</th></tr>\n"
            . "<tr><td>John O&apos;Connor</td><td>New York</td><td>Active</td></tr>\n"
            . "<tr><td>Jane &lt;script&gt;</td><td>Los Angeles</td><td>Pending</td></tr>\n"
            . "<tr><td>Tom &amp; Jerry</td><td>Vancouver</td><td>Active</td></tr>\n",
            $output,
        );
    }

    public function testReadmeHtmlTableKeysAreSmartStringsInHtmlMode(): void
    {
        $rows = SmartArray::new([['name' => "John O'Connor"]])->asHtml();

        $field = $rows->first()->keys()->first();

        $this->assertInstanceOf(SmartString::class, $field);
        $this->assertSame('name', $field->value());
    }

    public function testReadmeHtmlTableShowsPlaceholderRowWhenEmpty(): void
    {
        $rows = SmartArrayHtml::new([]);

        [, $output] = $this->captureOutput(static function () use ($rows): void {
            if ($rows->isNotEmpty()) {
                echo '<thead>';
            }
            foreach ($rows as $row) {
                echo '<tr>';
            }
            if ($rows->isEmpty()) {
                echo '<tr><td colspan="3">No records found</td></tr>';
            }
        });

        $this->assertSame('<tr><td colspan="3">No records found</td></tr>', $output);
    }

    //endregion
    //region README: Creating Grid Layouts with isFirst() and isLast()

    public function testReadmeGridExampleWrapsRowsInTableMarkup(): void
    {
        $users = SmartArray::new(self::quickStartRecords())->asHtml();

        [, $output] = $this->captureOutput(static function () use ($users): void {
            foreach ($users as $user) {
                if ($user->isFirst()) {
                    echo "<table border='1' cellpadding='10' style='text-align: center'>\n<tr>\n";
                }
                echo "<td><h1>$user->name</h1>$user->city</td>\n";
                if ($user->isLast()) {
                    echo "</tr>\n</table>\n";
                }
            }
        });

        $this->assertSame(
            "<table border='1' cellpadding='10' style='text-align: center'>\n<tr>\n"
            . "<td><h1>John O&apos;Connor</h1>New York</td>\n"
            . "<td><h1>Xena &quot;X&quot; Smith</h1>Los Angeles</td>\n"
            . "<td><h1>Tom &amp; Jerry</h1>Vancouver</td>\n"
            . "</tr>\n</table>\n",
            $output,
        );
    }

    //endregion
    //region README: Debugging and Help

    public function testReadmePrintRShowsNestedStructure(): void
    {
        $users = SmartArrayHtml::new([
            ['id' => 10, 'name' => "John O'Connor", 'city' => 'New York'],
            ['id' => 20, 'name' => 'Tom & Jerry',   'city' => 'Vancouver'],
        ]);

        [, $output] = $this->captureOutput(static function () use ($users): void {
            print_r($users);
        });

        $this->assertSame(
            "Itools\SmartArray\SmartArrayHtml Object\n"
            . "(\n"
            . "    [0] => Itools\SmartArray\SmartArrayHtml Object\n"
            . "        (\n"
            . "            [id] => 10\n"
            . "            [name] => John O'Connor\n"
            . "            [city] => New York\n"
            . "        )\n"
            . "\n"
            . "    [1] => Itools\SmartArray\SmartArrayHtml Object\n"
            . "        (\n"
            . "            [id] => 20\n"
            . "            [name] => Tom & Jerry\n"
            . "            [city] => Vancouver\n"
            . "        )\n"
            . "\n"
            . ")\n",
            $output,
        );
    }

    // help() prints the whole of src/help.txt, so the assertions are stable anchors
    // (wrapper, first heading, one section, last line) instead of the full text
    public function testReadmeHelpPrintsTheMethodReference(): void
    {
        $users = SmartArrayHtml::new([['id' => 10]]);

        [, $output] = $this->captureOutput(static function () use ($users): void {
            $users->help();
        });

        $this->assertStringStartsWith("\n<xmp>\n", $output);
        $this->assertStringContainsString('SmartArray: Enhanced Arrays with Automatic HTML Encoding and Chainable Methods', $output);
        $this->assertStringContainsString('Sorting & Filtering', $output);
        $this->assertStringContainsString('For more details see SmartArray readme.md', $output);
        $this->assertStringEndsWith("</xmp>\n", $output);
    }

    //endregion
    //region README: Method Reference

    /** "filter() - Removes falsey values ("", 0, empty array, etc)" - the no-argument form, which help.txt omits */
    public function testReadmeMethodReferenceFilterWithNoArgumentsRemovesFalseyValues(): void
    {
        $mixed = SmartArray::new([0, 1, '', 'a', null, '0', false]);

        $this->assertSame([1 => 1, 3 => 'a'], $mixed->filter()->toArray(), 'keys are preserved');
    }

    //endregion
    //region help.txt: Accessing Elements and Original Values

    public function testHelpTxtAccessingElementsChainsSmartStringMethods(): void
    {
        $users = SmartArrayHtml::new([
            [
                'name'    => "John O'Connor",
                'bio'     => '<p>Writes <b>things</b> &amp; stuff about PHP, MySQL, and the occasional bit of JavaScript, at length, forever.</p>',
                'wysiwyg' => '<b>trusted markup</b>',
            ],
        ]);

        [, $output] = $this->captureOutput(static function () use ($users): void {
            foreach ($users as $user) {
                echo "Name: $user->name\n";
                echo "Bio: {$user->bio->textOnly()->maxChars(120, '...')}\n";
            }
        });

        $this->assertSame(
            "Name: John O&apos;Connor\n"
            . "Bio: Writes things &amp; stuff about PHP, MySQL, and the occasional bit of JavaScript, at length, forever.\n",
            $output,
        );
    }

    public function testHelpTxtOriginalValuesReturnUnencodedData(): void
    {
        $user = SmartArrayHtml::new(['name' => "O'Brien", 'wysiwyg' => '<b>bold</b>']);

        $this->assertSame(['name' => "O'Brien", 'wysiwyg' => '<b>bold</b>'], $user->toArray());
        $this->assertSame("O'Brien", $user->name->value());

        [, $output] = $this->captureOutput(static function () use ($user): void {
            echo "Bio: {$user->wysiwyg->rawHtml()}";
        });

        $this->assertSame('Bio: <b>bold</b>', $output);
    }

    //endregion
    //region help.txt: Creating SmartArrays and Converting Between Types

    public function testHelpTxtCreateAndConvertBetweenModes(): void
    {
        $data = SmartArray::new([1, 2, 3]);

        $this->assertSame([1, 2, 3], $data->toArray());
        $this->assertInstanceOf(SmartArrayHtml::class, $data->asHtml());
        $this->assertInstanceOf(SmartArray::class, $data->asHtml()->asRaw());
        $this->assertInstanceOf(SmartArrayHtml::class, SmartArrayHtml::new($data->toArray()));
        $this->assertSame([1, 2, 3], SmartArrayHtml::new($data->toArray())->toArray(), 'same result as ->asHtml()');
    }

    //endregion
    //region help.txt: Example Workflow

    public function testHelpTxtExampleWorkflowFiltersOnRawValuesThenSorts(): void
    {
        $records = [
            ['name' => 'Zoe',        'active' => 1],
            ['name' => 'Adam',       'active' => 0],
            ['name' => 'Bob & Sons', 'active' => 1],
        ];

        $users = SmartArray::new($records)
            ->filter(fn($u) => $u['active'])
            ->sortBy('name');

        $this->assertSame([
            ['name' => 'Bob & Sons', 'active' => 1],
            ['name' => 'Zoe',        'active' => 1],
        ], $users->toArray());

        [, $output] = $this->captureOutput(static function () use ($users): void {
            foreach ($users->asHtml() as $user) {
                echo "Name: $user->name\n";
            }
        });

        $this->assertSame("Name: Bob &amp; Sons\nName: Zoe\n", $output);
    }

    //endregion
    //region help.txt: Value Access

    public function testHelpTxtValueAccessReadsByKeyAndPosition(): void
    {
        $row = SmartArray::new(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(1, $row->a, 'property syntax');
        $this->assertSame(2, $row->get('b'), 'get(key)');
        $this->assertSame('fallback', $row->get('missing', 'fallback'), 'get(key, default)');
        $this->assertSame(1, $row->first());
        $this->assertSame(3, $row->last());
        $this->assertSame(1, $row->at(0), 'at(0) is the first element');
        $this->assertSame(3, $row->at(-1), 'at(-1) is the last element');
    }

    public function testHelpTxtSetStoresValueByKey(): void
    {
        $row = SmartArray::new(['a' => 1]);

        $row->set('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], $row->toArray());
    }

    public function testHelpTxtGetRawValueUnwrapsSmartObjectsOnly(): void
    {
        $row = SmartArrayHtml::new(['name' => "John O'Connor"]);

        $this->assertSame("John O'Connor", SmartArray::getRawValue($row->name));
        $this->assertSame(['name' => "John O'Connor"], SmartArray::getRawValue($row));
        $this->assertSame('plain string', SmartArray::getRawValue('plain string'));
        $this->assertSame(42, SmartArray::getRawValue(42));
    }

    //endregion
    //region help.txt: Array Information

    public function testHelpTxtArrayInformationMethods(): void
    {
        $list  = SmartArray::new(['x', 'y', 'z']);
        $empty = SmartArray::new([]);

        $this->assertSame(3, $list->count());
        $this->assertFalse($list->isEmpty());
        $this->assertTrue($list->isNotEmpty());
        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isNotEmpty());
    }

    public function testHelpTxtContainsUsesLooseComparison(): void
    {
        $numbers = SmartArray::new([1, 2, 3]);

        $this->assertTrue($numbers->contains('2'), "'2' == 2");
        $this->assertFalse($numbers->contains(9));
    }

    //endregion
    //region help.txt: Position and Layout

    public function testHelpTxtPositionMethodsReportPlaceInParent(): void
    {
        $rows = SmartArray::new([['n' => 1], ['n' => 2], ['n' => 3]]);

        $this->assertSame(1, $rows->first()->position());
        $this->assertTrue($rows->first()->isFirst());
        $this->assertFalse($rows->first()->isLast());
        $this->assertSame(3, $rows->last()->position());
        $this->assertFalse($rows->last()->isFirst());
        $this->assertTrue($rows->last()->isLast());
    }

    //endregion
    //region help.txt: Sorting and Filtering

    public function testHelpTxtSortReindexesKeys(): void
    {
        $list = SmartArray::new([3 => 'c', 1 => 'a', 2 => 'b']);

        $this->assertSame(['a', 'b', 'c'], $list->sort()->toArray());
    }

    public function testHelpTxtSortByFieldAndFlags(): void
    {
        $rows = SmartArray::new([['n' => 'item10'], ['n' => 'item9'], ['n' => 'item1']]);

        $this->assertSame(['item1', 'item10', 'item9'], $rows->sortBy('n')->column('n')->toArray(), 'SORT_REGULAR default');
        $this->assertSame(['item1', 'item10', 'item9'], $rows->sortBy('n', SORT_STRING)->column('n')->toArray());
        $this->assertSame(['item1', 'item9', 'item10'], $rows->sortBy('n', SORT_NATURAL)->column('n')->toArray());
    }

    public function testHelpTxtUniqueKeepsFirstOccurrence(): void
    {
        $list = SmartArray::new(['a', 'b', 'a', 'c']);

        $this->assertSame([0 => 'a', 1 => 'b', 3 => 'c'], $list->unique()->toArray(), 'keys of the kept values are preserved');
    }

    public function testHelpTxtFilterCallbackReceivesRawValues(): void
    {
        $rows = SmartArrayHtml::new([['n' => 1], ['n' => 2], ['n' => 3]]);

        $result = $rows->filter(fn($row) => $row['n'] > 1);

        $this->assertSame([1 => ['n' => 2], 2 => ['n' => 3]], $result->toArray());
    }

    public function testHelpTxtWhereFamilyFiltersRows(): void
    {
        $rows = SmartArray::new([
            ['status' => 'active', 'tags' => "\tphp\tsql\t"],
            ['status' => 'draft',  'tags' => "\tphp\t"],
        ]);

        $this->assertSame([0 => ['status' => 'active', 'tags' => "\tphp\tsql\t"]], $rows->where('status', 'active')->toArray());
        $this->assertSame([1 => ['status' => 'draft', 'tags' => "\tphp\t"]], $rows->whereNot('status', 'active')->toArray());
        $this->assertSame([0 => ['status' => 'active', 'tags' => "\tphp\tsql\t"]], $rows->whereInList('tags', 'sql')->toArray());
    }

    //endregion
    //region help.txt: Array Transformation

    public function testHelpTxtKeysAndValues(): void
    {
        $row = SmartArray::new(['a' => 1, 'b' => 2]);

        $this->assertSame(['a', 'b'], $row->keys()->toArray());
        $this->assertSame([1, 2], $row->values()->toArray());
    }

    public function testHelpTxtIndexByAndGroupBy(): void
    {
        $rows = SmartArray::new([
            ['id' => 1, 'type' => 'a'],
            ['id' => 2, 'type' => 'a'],
        ]);

        $this->assertSame(['a' => ['id' => 2, 'type' => 'a']], $rows->indexBy('type')->toArray(), 'duplicates overwrite');
        $this->assertSame(['a' => [['id' => 1, 'type' => 'a'], ['id' => 2, 'type' => 'a']]], $rows->groupBy('type')->toArray(), 'duplicates group');
    }

    public function testHelpTxtColumnVariantsMatchArrayColumn(): void
    {
        $people = SmartArray::new([
            ['id' => 3, 'name' => 'Amy', 'city' => 'NY'],
            ['id' => 5, 'name' => 'Bob', 'city' => 'LA'],
        ]);

        $this->assertSame(['Amy', 'Bob'], $people->column('name')->toArray());
        $this->assertSame([3 => 'Amy', 5 => 'Bob'], $people->column('name', 'id')->toArray());
        $this->assertSame([
            3 => ['id' => 3, 'name' => 'Amy', 'city' => 'NY'],
            5 => ['id' => 5, 'name' => 'Bob', 'city' => 'LA'],
        ], $people->column(null, 'id')->toArray());
    }

    public function testHelpTxtColumnAtReadsByPositionIgnoringKeyNames(): void
    {
        $people = SmartArray::new([
            ['id' => 3, 'name' => 'Amy', 'city' => 'NY'],
            ['id' => 5, 'name' => 'Bob', 'city' => 'LA'],
        ]);

        $this->assertSame([3, 5], $people->columnAt(0)->toArray(), '0 is the first column');
        $this->assertSame(['NY', 'LA'], $people->columnAt(-1)->toArray(), '-1 is the last column');
    }

    public function testHelpTxtImplodeDefaultsToEmptySeparator(): void
    {
        $list = SmartArray::new(['a', 'b', 'c']);

        $this->assertSame('abc', $list->implode());
        $this->assertSame('a, b, c', $list->implode(', '));
    }

    public function testHelpTxtMapCallbackReceivesRawValues(): void
    {
        $list = SmartArrayHtml::new([1, 2, 3]);

        $this->assertSame([2, 4, 6], $list->map(fn($v) => $v * 2)->toArray());
    }

    public function testHelpTxtMergeRenumbersNumericKeysAndOverwritesStringKeys(): void
    {
        $list = SmartArray::new(['a' => 1, 0 => 'x']);

        $this->assertSame(['a' => 2, 0 => 'x', 1 => 'y'], $list->merge(['a' => 2, 0 => 'y'])->toArray());
    }

    //endregion
    //region help.txt: Database Operations

    public function testHelpTxtMysqliReturnsQueryMetadata(): void
    {
        $rows = new SmartArray([['id' => 1]], ['mysqli' => ['affected_rows' => 3, 'insert_id' => 77]]);

        $this->assertSame(['affected_rows' => 3, 'insert_id' => 77], $rows->mysqli());
        $this->assertSame(3, $rows->mysqli('affected_rows'));
        $this->assertSame(77, $rows->mysqli('insert_id'));
        $this->assertNull($rows->mysqli('errno'), 'keys the database layer did not supply are null');
        $this->assertSame([], SmartArray::new([['id' => 1]])->mysqli(), 'no metadata on arrays built from plain PHP arrays');
    }

    public function testHelpTxtLoadReturnsRelatedRecords(): void
    {
        $handler = static fn($row, string $field): array => [[['order_id' => 5, 'total' => 9.99]], ['affected_rows' => 1]];
        $user    = new SmartArray(['id' => 1, 'name' => 'Amy'], ['loadHandler' => $handler]);

        $orders = $user->load('orders');

        $this->assertInstanceOf(SmartArray::class, $orders);
        $this->assertSame([['order_id' => 5, 'total' => 9.99]], $orders->toArray());
        $this->assertSame(['affected_rows' => 1], $orders->mysqli(), 'the handler supplies fresh query metadata');
    }

    public function testHelpTxtLoadWithoutHandlerThrowsCallerException(): void
    {
        $this->expectException(CallerException::class);
        $this->expectExceptionMessage("load(): no load handler is set. Handlers are normally provided by the database layer (ZenDB); arrays created directly don't have one.");

        SmartArray::new(['id' => 1])->load('orders');
    }

    public function testHelpTxtLoadOnEmptyArrayReturnsSmartNull(): void
    {
        $this->assertSmartNull(SmartArray::new([])->load('orders'));
    }

    //endregion
    //region help.txt: Error Handling

    public function testHelpTxtOrThrowThrowsWhenEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No records found');

        SmartArray::new([])->orThrow('No records found');
    }

    public function testHelpTxtOrThrowChainsWhenNotEmpty(): void
    {
        $rows = SmartArray::new([['id' => 1], ['id' => 2]]);

        $this->assertSame([['id' => 1], ['id' => 2]], $rows->orThrow('No records found')->toArray());
    }

    //endregion
    //region help.txt: Debugging

    // debug() prints a formatted dump; the assertions are stable anchors (mode line,
    // a value, the metadata block) instead of the exact column padding
    public function testHelpTxtDebugShowsValuesAndMysqliMetadata(): void
    {
        $rows = new SmartArray([['id' => 1]], ['mysqli' => ['affected_rows' => 3, 'insert_id' => 77]]);

        [, $output] = $this->captureOutput(static function () use ($rows): void {
            $rows->debug();
        });

        $this->assertStringStartsWith("\n<xmp>\n", $output);
        $this->assertStringContainsString('Values are returned **as-is** on access', $output);
        $this->assertStringContainsString("'id' => 1", $output);
        $this->assertStringContainsString('MySQLi Metadata [', $output);
        $this->assertStringContainsString("'affected_rows' => 3", $output);
        $this->assertStringEndsWith("</xmp>\n", $output);
    }

    public function testHelpTxtDebugOnHtmlModeNamesTheMode(): void
    {
        $rows = SmartArrayHtml::new([['id' => 1]]);

        [, $output] = $this->captureOutput(static function () use ($rows): void {
            $rows->debug();
        });

        $this->assertStringContainsString('SmartArrayHtml - Values are returned as **SmartStrings** on access', $output);
    }

    //endregion
}
