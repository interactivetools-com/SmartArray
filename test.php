<?php

require "vendor/autoload.php";
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;


$records = [
    ['id' => 10, 'name' => "John O'Connor",  'city' => 'New York'],
    ['id' => 15, 'name' => 'Xena "X" Smith', 'city' => 'Los Angeles'],
    ['id' => 20, 'name' => 'Tom & Jerry',    'city' => 'Vancouver'],
];
$users = SmartArray::new($records);

print_r($users);


foreach ($users as $user) {
    if ($user->isFirst())       { echo "<table border='1' cellpadding='10' style='text-align: center'>\n<tr>\n"; }
    echo "<td><h1>$user->name</h1>$user->city</td>\n";
    if ($user->isMultipleOf(2)) { echo "</tr>\n<tr>\n"; }
    if ($user->isLast())        { echo "</tr>\n</table>\n"; }
}


if ($users->isNotEmpty()) {
    echo "<table border='1' cellpadding='10' style='text-align: center'>\n";
    foreach ($users->chunk(2) as $row) {
        echo "<tr>\n";
        foreach ($row as $user) {
            echo "<td><h1>$user->name</h1>$user->city</td>\n";
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
}

foreach ($users->chunk(2) as $row) {
    foreach ($row as $user) {

    }
}

?>


<table border='1' cellpadding='10' style='text-align: center'>
    <tr>
        <td><h1>John O&apos;Connor</h1>New York</td>
        <td><h1>Xena &quot;X&quot; Smith</h1>Los Angeles</td>
    </tr>
    <tr>
        <td><h1>Tom &amp; Jerry</h1>Vancouver</td>
    </tr>
</table>

<?php

print "done";
exit;

$array = [
        ['id' => 1, null => 'Alice'],
        ['id' => 2],
        ['id' => 3, null => 'Charlie'],
    ];

print_r(array_column($array, null));

SmartArray::new($array)->implode(",");

exit;

// - Test JSON

// enable max error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '0');
ini_set('html_errors', '0');

header("Content-Type: text/plain");
//echo "<body style='background-color: #000; color: #CCC'><xmp>";

$empty   = new SmartArray();
$records = new SmartArray(getTestRecords());
$record  = $records->get(0);

$r = new SmartArray([
        'North America' => [
            'Canada' => [
                'British Columbia' => ['Vancouver', 'Victoria', 'Kelowna', 'Nanaimo'],
                'Alberta'          => ['Calgary', 'Edmonton', 'Red Deer'],
            ],
            'United States' => [
                'California' => ['Los Angeles', 'San Francisco', 'San Diego'],
                'New York'    => ['New York City', 'Buffalo', 'Rochester'],
                'Florida'     => ['Miami', 'Orlando', 'Tampa'],
            ],
        ],
    ]);

print_r($r);
exit;

print_r($empty->chunk(4));

foreach ($records->chunk(4) as $recordChunk) {
    foreach ($recordChunk as $r) {
        print "isFirst: {$r->isFirst()}, isLast: {$r->isLast()}, position: {$r->position()}\n";
        print_r($r);
    }
    echo "-----------------------\n";
}

exit;


$a = ['0' => 'zero', '1' => 'one', '2' => 'two', '3' => 'three'];
$a = [0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three'];
$a = ['zero','one','two','three'];

echo json_encode(new ArrayObject(['zero','one','two','three'])); // returns: {"0":"zero","1":"one","2":"two","3":"three"}
echo json_encode(new SmartArray(['zero','one','two','three']));  // returns: ["zero","one","two","three"]
echo "\n";


exit;




print "done</xmp>";
exit;

//print_r($records3->toArray());
echo "---";
$records3[1]->name = "Da'v'e";
var_dump(isset($records3[2]->name));
print_r("Hello {$records3->get(1)->name}");
echo "---";
//$records3->pluck('isFirst2');
//echo("hello $r['isFirst2'] world");
exit;
echo "---";
$r = $records3->pluck('isFirst2');
echo "---";
print_r($r);
print_r($r->toArray());

print "done";
exit;

function getTestRecords(): array
{
    return [
        [
            'html'        => "<img alt='\"'>",
            'int'         => 10,
            'float'       => 5.7,
            'string'      => '&nbsp;',
            'bool'        => true,
            'null'        => null,
            'isFirst'     => 'C',
        ],
        [
            'html'        => '<p>"It\'s"</p>',
            'int'         => 20,
            'float'       => 1.23,
            'string'      => '"green"',
            'bool'        => false,
            'null'        => null,
            'isFirst'     => 'Q',
        ],
        [
            'html'        => "<hr class='line'>",
            'int'         => 30,
            'float'       => -16.7,
            'string'      => '<blue>',
            'bool'        => false,
            'null'        => null,
            'isFirst'     => 'K',
        ],
        [
            'html'        => "<img alt='\"'>",
            'int'         => 40,
            'float'       => 5.7,
            'string'      => '&nbsp;',
            'bool'        => true,
            'null'        => null,
            'isFirst'     => 'C',
        ],
        [
            'html'        => '<p>"It\'s"</p>',
            'int'         => 50,
            'float'       => 1.23,
            'string'      => '"green"',
            'bool'        => false,
            'null'        => null,
            'isFirst'     => 'Q',
        ],
        [
            'html'        => "<hr class='line'>",
            'int'         => 60,
            'float'       => -16.7,
            'string'      => '<blue>',
            'bool'        => false,
            'null'        => null,
            'isFirst'     => 'K',
        ],
        [
            'html'        => "<img alt='\"'>",
            'int'         => 70,
            'float'       => 5.7,
            'string'      => '&nbsp;',
            'bool'        => true,
            'null'        => null,
            'isFirst'     => 'C',
        ],
        [
            'html'        => '<p>"It\'s"</p>',
            'int'         => 80,
            'float'       => 1.23,
            'string'      => '"green"',
            'bool'        => false,
            'null'        => null,
            'isFirst'     => 'Q',
        ],
        [
            'html'        => "<hr class='line'>",
            'int'         => 90,
            'float'       => -16.7,
            'string'      => '<blue>',
            'bool'        => false,
            'null'        => null,
            'isFirst'     => 'K',
        ],
    ];
}

    exit;

$array = array(11 => 'one',
               3 => 'two',
               1 => 'three');
//$array = [];
$arrayobject = new ArrayObject($array);
$iterator = $arrayobject->getIterator();

//if($iterator->valid()){
//    $iterator->seek(0);            // expected: two, output: two
    var_dump($iterator->current());    // two
    var_dump($iterator->valid());    // two
//}

// $users->first()->name;
// $results->getFirst()->name;
// $results->first()->name->value();
// $results->row(0)->name->value();
// $resultSet->first()->name->value();
// $resultSet->row(0)->name->value();
// $records->first()->name->value();
// $records->row(0)->name->value();
// $users->first()->name->value();
// $users->row(0)->name->value();

// DB::query(...)->first()->name->value();
// DB::query(...)->firstRow()->name->value();
// DB::query(...)->firstRow()->get("name")->value();
// DB::query(...)->firstRow()->col("name")->value();
// DB::query(...)->get(0)->col("name")->value();
// DB::query(...)->row(0)->col("name")->value();
// DB::fetchQuery(...)->name;
// column is confusing because it sounds like array_column
// $records->column('num')

$users = $users->map(fn($user) => $user->name->value() );

print "done";
exit;





__halt_compiler();

$records = [];
foreach ($records as $record) {

    if ($record->counter() <= 3) {
        // show heading
        continue;
    }

    if ($record->number() <= 3) {
        // show heading
        continue;
    }

    if ($record->counter() <= 3) {
        // show heading
        continue;
    }

    if ($record->counter() <= 3) {
        // show heading
        continue;
    }

    // show other records

}
$record = (object) [];


// Yes
if ($record->position() <= 3) { }
if ($record->order() <= 3) { }

if ($record->position() <= 3) { }
if ($record->orderNum() <= 3) { }
if ($record->number() <= 3) { }
if ($record->counter() <= 3) { }
if ($record->countNum() <= 3) { }



// Strong Maybe
if ($record->positionNum() <= 3) { }
if ($record->positionNumber() <= 3) { }
if ($record->positionOrder() <= 3) { }
if ($record->listOrder() <= 3) { }

// Maybe
if ($record->itemNumber() <= 3) { }
if ($record->orderNum() <= 3) { }

// No
if ($record->index() <= 3) { }
if ($record->count() <= 3) { }
if ($record->offset() <= 3) { }
if ($record->rank() <= 3) { }
if ($record->sequence() <= 3) { }
if ($record->sequenceNum() <= 3) { }
if ($record->sequenceNumber() <= 3) { }

exit;

$arrayObject = new ArrayObject([1]);

// Attempt to get the first value
$i = $arrayObject->getIterator();
$firstValue = $i->current();

var_dump([$firstValue, $i->valid()]); // Outputs: bool(false)

exit;

$users = [
    ['name' => 'John O\'Reilly', 'age' => 30, 'city' => 'New York', 'country' => 'USA', 'isFirst' => 'A'],
    ['name' => 'Jane Doe', 'age' => 25, 'city' => 'Los Angeles', 'country' => 'USA', 'isFirst' => 'A'],
    ['name' => 'Juan Perez', 'age' => 35, 'city' => 'Mexico City', 'country' => 'Mexico', 'isFirst' => 'A'],
];

$records = [
    [ 'int' => 7, 'float' => 5.7,   'string' => '&red;',   'bool' => true,  'null' => null, 'obj' => (object) ["1" => 1],        'smartString' => SmartString::new("seven") ],
    [ 'int' => 0, 'float' => 1.23,  'string' => '"green"', 'bool' => false, 'null' => null, 'obj' => (object) [16 => "sixteen"], 'smartString' => SmartString::new(16) ],
    [ 'int' => 1, 'float' => -16.7, 'string' => '<blue>',  'bool' => false, 'null' => null, 'obj' => (object) [2.3 => "23"],     'smartString' => SmartString::new(3.12) ],
];

$r3 = new SmartArray($records);
$r0 = new SmartArray();

$r = new SmartArray(["one", "<blue>", "this'that"]);
print_r($r->join(', '));

foreach ($r as $key => $value) {
    echo "\n$key = $value->value()";
}
exit;

print_r(['empty', empty($r3), empty($r0)]);
print_r(['count', count($r3), count($r0)]);

print_r(get_object_vars($r3));

exit;

$users = [
    ['name' => 'John O\'Reilly', 'age' => 30, 'city' => 'New York', 'country' => 'USA', 'isFirst' => 'A'],
    ['name' => 'Jane Doe', 'age' => 25, 'city' => 'Los Angeles', 'country' => 'USA', 'isFirst' => 'A'],
    ['name' => 'Juan Perez', 'age' => 35, 'city' => 'Mexico City', 'country' => 'Mexico', 'isFirst' => 'A'],
];



$users = new SmartArray(null);


print_r($users);
exit;

//$users[1]['country']->public = "Vancouver";

//print_r([property_exists($users, 'isFirst')]);

foreach ($users as $user) {

    $user->isFirst = "baz";
    $user->name = "foo";
    print "\$user->isFirst(): " . $user->isFirst() . "\n";
    print "\$user->isLast(): " . $user->isLast() . "\n";
    print "\$user->order(): " . $user->isLast() . "\n";
    print_r($user);
}

print "done";
exit;


//header("Content-Type: text/plain");

$name = SmartString::new('<10% OFF "SALE"');
$name->help();
SmartString::help();
exit;

$output = $name->jsonEncode();
$output = str_replace("&", "&&ZeroWidthSpace;", $output);
echo $output;
exit;
echo " $name->jsonEncode ";
exit;

print_r($name);
var_dump($name);

exit;



$invalid = SmartString::new("not a date");

print_r($invalid->dateFormat());

echo $invalid->dateFormat()->or("Invalid date"); // "Invalid date"
echo $invalid->dateFormat()->or($invalid);       // "not a date"

$str = SmartString::new("  <p>More text and HTML than needed</p>  ");
echo $str->textOnly()->maxWords(3); // Output: Too much text...

echo SmartString::new("Hello\nWorld")->nl2br();
exit;

$j = SmartString::new( "<10% 'SALE'>");
echo $j;
exit;

SmartString::$phoneFormat = [
    ['digits' => 10, 'format' => '###.###.####'],
    ['digits' => 11, 'format' => '#.###.###.####'],
];


$phone = SmartString::new("604-828-334822");

echo $phone->help();

print "done";

exit;


echo $phone->phoneFormat()->or($phone);

$str = "The quick, brown fox jumps over the lazy dog, and the dog is not amused. Yet, the fox is very happy.";
$str = "the'quickbrownfoxjumpsoverthelazydogandthedogisnotamusedyetthefoxisveryhappy";
//$str = NULL;
$s = SmartString::new($str);
print_r($s);
var_dump($s);
var_export($s, true);
print_r($s);
$s->help();
exit;

$s->help();
$s->help();
$s->help();

echo $s->maxChars(14);

print "\ndone";
exit;


$fruit = ['apple', 'pear'];

print_r(array_slice($fruit, 0, 1,11));
exit;

$n1 = new SmartString(8.8);
$n2 = new SmartString(5.5);
echo $n1->value() - $n2->value();
//echo 8.8 - 5.5;
echo "<br>\n";
exit;

// enable maximum error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '0');


$name = "John O'Reilly";
$record = [
    'num' => 1,
    'name' => $name,
    'age' => 30,
    'city' => 'New York',
    'country' => 'USA',
    'email' => 'john@example.com',
];
$records = [$record, $record, $record, $record, $record, $record, $record, $record, $record, $record];

$r = SmartString::new($records);

print_r($r->{0}->name);


foreach ($r as $rowIndex => $row) {
    echo "Row: $rowIndex\n";
  foreach ($row as $key => $value) {
    echo "  $key: $value\n";
  }
  echo "\n";
}
exit;

    $s = new SmartString("<Hello> 'World' & \"Goodbye\" # @ ? = + %20");
    $name = SmartString::new("O'Reilly &amp; Sons");

print_r($name);

print "\nDone";
exit;

$req = SmartString::new($_REQUEST);
$req = SmartString::create($_REQUEST);


$req = SmartString::new("hello world");
$req = SmartString::create("hello world");




$s->apply(fn($v) => $v ?: "Empty");

print_r($s);
print_r($s);
print_r($s);
print_r($s);

print "done";
exit;
