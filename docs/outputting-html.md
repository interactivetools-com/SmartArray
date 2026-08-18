<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Outputting HTML

Fields HTML-encode themselves on output. This page covers how that works
and the deliberate ways around it.

Contents:

- [How Auto-Encoding Works](#how-auto-encoding-works)
- [Trusted HTML: rawHtml()](#trusted-html-rawhtml)
- [Loop Layout: isFirst(), isLast(), position()](#loop-layout-isfirst-islast-position)
- [Keys Are Never Encoded](#keys-are-never-encoded)

## How Auto-Encoding Works

A field holds your original value, exactly as it came from the database,
and produces the HTML-encoded version only at the moment you output it.
That means what you display is safe, what you compare or calculate with is
still the real value, and no time is spent encoding fields you never show:

```php
use Itools\SmartArray\SmartArrayHtml;

$article = SmartArrayHtml::new(['title' => 'Tips & Tricks']);

echo $article->title;           // Tips &​amp; Tricks
echo $article->title->value();  // Tips & Tricks (the original value, for logic)
```

That covers every string context, so there's no encoding call to remember
and no way to forget one. The rest of this page is about the places where
you want something other than the default.

## Trusted HTML: rawHtml()

Some fields hold real HTML that's meant to render, most often WYSIWYG
editor content. Encoding would show the tags as text, so output those
fields with `rawHtml()`:

```php
$article = SmartArrayHtml::new(['body' => '<p>Use <b>bold</b> for emphasis.</p>']);

echo $article->body;             // &​lt;p&​gt;Use &​lt;b&​gt;bold&​lt;/b&​gt; for emphasis.&​lt;/p&​gt;
echo $article->body->rawHtml();  // <p>Use <b>bold</b> for emphasis.</p>
```

Use it for content you trust, like your own CMS's editor fields; visitor
input stays on the default encoded path.

More output helpers (`nl2br()`, `appendHtml()`, `wrapHtml()`,
`urlEncode()`, `jsonEncode()`) are on SmartString's
[Encoding and HTML](https://github.com/interactivetools-com/SmartString/blob/main/docs/encoding-and-html.md)
page.

## Loop Layout: isFirst(), isLast(), position()

Every row knows where it sits in its collection, which handles the layout
decisions inside loops: separators between items, wrappers around the
whole list, special treatment for the first few rows.

| Method       | Returns                              |
|--------------|--------------------------------------|
| `isFirst()`  | true for the first row               |
| `isLast()`   | true for the last row                |
| `position()` | the row's position, counting from 1  |

Separators go after every row except the last:

```php
$tags = SmartArrayHtml::new([['name' => 'PHP'], ['name' => 'MySQL'], ['name' => 'Tutorials']]);

foreach ($tags as $tag) {
    echo $tag->name;
    if (!$tag->isLast()) {
        echo ', ';
    }
}
// PHP, MySQL, Tutorials
```

And `position()` singles out rows by rank, like featuring the newest
articles in a list:

```php
$articles = SmartArrayHtml::new([
    ['title' => 'Fall Fair Sept 20-21'],
    ['title' => 'New Trail Maps'],
    ['title' => 'Road Closures on Main St'],
    ['title' => 'Library Summer Hours'],
]);

foreach ($articles as $article) {
    $class = $article->position() <= 3 ? 'featured' : 'normal';
    echo "<li class='$class'>$article->title</li>\n";
}
// <li class='featured'>Fall Fair Sept 20-21</li>
// <li class='featured'>New Trail Maps</li>
// <li class='featured'>Road Closures on Main St</li>
// <li class='normal'>Library Summer Hours</li>
```

## Keys Are Never Encoded

Auto-encoding covers values, not keys: `foreach` hands keys back as plain
values, so they stay usable for lookups and comparisons. That matters when
the keys came from user data, like grouping by a user-entered field and
echoing the group names:

```php
$users = SmartArrayHtml::new([
    ['name' => 'Jean', 'city' => "St. John's"],
    ['name' => 'Tom',  'city' => 'Ottawa'],
]);

$usersByCity = $users->groupBy('city');

// WRONG - foreach keys are plain values, so user-entered text lands in the page unencoded
foreach ($usersByCity as $city => $residents) {
    echo "<option>$city</option>\n";  // <option>St. John's</option>
}

// RIGHT - keys() hands the keys back as fields, so they encode like any other value
foreach ($usersByCity->keys() as $city) {
    echo "<option>$city</option>\n";  // <option>St. John&​apos;s</option>
}
```

---

[← Documentation Index](README.md) | [Next: Filtering and Sorting →](filtering-and-sorting.md)
