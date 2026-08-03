<?php

use App\Services\OdkMarkdownService;

beforeEach(function () {
    $this->converter = new OdkMarkdownService;
});

it('converts odk markdown to html', function (string $odkMarkdown, string $expectedHtml) {
    expect($this->converter->toHtml($odkMarkdown))->toBe($expectedHtml);
})->with([
    'bold' => ['Hello **bold** text', '<p>Hello <strong>bold</strong> text</p>'],
    'italic' => ['Hello _italic_ text', '<p>Hello <em>italic</em> text</p>'],
    'single line break' => ["line1\nline2", '<p>line1<br>line2</p>'],
    'blank line' => ["para1\n\npara2", '<p>para1<br><br>para2</p>'],
    'crlf normalised' => ["line1\r\nline2", '<p>line1<br>line2</p>'],
    'html is escaped' => ['a <b> b', '<p>a &lt;b&gt; b</p>'],
    'hash is not a header' => ['# not a header', '<p># not a header</p>'],
    'number is not a list' => ['1. not a list', '<p>1. not a list</p>'],
    'snake_case names are not italicised' => ['use enum_intro here', '<p>use enum_intro here</p>'],
]);

it('returns an empty string when converting blank values to html', function (?string $value) {
    expect($this->converter->toHtml($value))->toBe('');
})->with([null, '', ' ']);

it('converts editor html to odk markdown', function (string $html, string $expectedMarkdown) {
    expect($this->converter->fromHtml($html))->toBe($expectedMarkdown);
})->with([
    'strong' => ['<p>Hello <strong>bold</strong></p>', 'Hello **bold**'],
    'b tag' => ['<p>Hello <b>bold</b></p>', 'Hello **bold**'],
    'em' => ['<p>Hello <em>italic</em></p>', 'Hello _italic_'],
    'i tag' => ['<p>Hello <i>italic</i></p>', 'Hello _italic_'],
    'br becomes newline' => ['<p>line1<br>line2</p>', "line1\nline2"],
    'paragraphs become blank lines' => ['<p>a</p><p>b</p>', "a\n\nb"],
    'headings are unwrapped' => ['<h2>Title</h2><p>body</p>', "Title\n\nbody"],
    'links are unwrapped' => ['<p>see <a href="https://example.org">this</a></p>', 'see this'],
    'underline is unwrapped' => ['<p><u>under</u></p>', 'under'],
    'styled spans are unwrapped' => ['<p><span style="color:red">red</span></p>', 'red'],
    'list items keep their text' => ['<ul><li>one</li><li>two</li></ul>', "one\ntwo"],
    'empty bold is dropped' => ['<p>a<strong></strong>b</p>', 'ab'],
    'nbsp becomes space' => ["<p>a\u{00A0}b</p>", 'a b'],
    'consecutive brs are kept' => ['<p>a<br><br><br>b</p>', "a\n\n\nb"],
    'utf8 text survives' => ['<p>Namaste — नमस्ते, ¿está de acuerdo?</p>', 'Namaste — नमस्ते, ¿está de acuerdo?'],
]);

it('returns an empty string when converting blank html', function (?string $value) {
    expect($this->converter->fromHtml($value))->toBe('');
})->with([null, '', '<p></p>', '<p><br></p>', "<p>\u{00A0}</p>"]);

it('round-trips stored text without changes', function (string $storedText) {
    expect($this->converter->fromHtml($this->converter->toHtml($storedText)))->toBe($storedText);
})->with([
    'plain text' => ['We would like to invite you to participate in this survey.'],
    'multiline' => ["Thank you for your time.\nYour answers are confidential."],
    'blank line between paragraphs' => ["First paragraph.\n\nSecond paragraph."],
    'bold and italic' => ['This is **very important** and _optional_.'],
    'markdown lookalike' => ['# heading-like line and 1. list-like line'],
    'literal html' => ['Do you agree? <yes/no>'],
]);
