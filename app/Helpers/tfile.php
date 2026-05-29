<?php

if (! function_exists('tfile')) {
    /**
     * Load a markdown file from resources/text/ and pass it through t() for translation.
     */
    function tfile(string $name): string
    {
        $path = resource_path("text/{$name}.md");
        $content = file_get_contents($path);

        return t($content);
    }
}
