# Bugo WP Export CLI Plugin

Hugo a static site generator written in GoLang: [https://gohugo.io](https://gohugo.io)

This repo is based on [https://github.com/benbalter/wordpress-to-jekyll-exporter](https://github.com/benbalter/wordpress-to-jekyll-exporter) 

## Features

* Converts all posts & pages from WordPress for use in Hugo
* Processes shortcodes
* Converts all `post_content` to Markdown Extra (using Markdownify)
* Converts all `post_meta` and fields within the `wp_posts` table to YAML front matter for parsing by Hugo.
* Converts all **Advanced Custom Fields** into front matter in your posts. May be duplicate data, but new keys.
* Metadata is converted markdown.
* All internal URls are converted to relative URLs.
* Exports private posts and drafts. They are marked as drafts as well and won't get published with Hugo.
* Generates a `config.yaml` with all settings in the `wp_options` table

## Usage with a self hosted WordPress installation

1. Place plugin in `/wp-content/plugins/` folder
2. Make sure `extension=zip.so` line is uncommented in your `php.ini`
3. Activate plugin in WordPress dashboard
4. Select `Export to Hugo` from the `Tools` menu

## Why CLI?

It's been my experience that it just runs faster. Server speeds and setup can vary wildly. 
Some hosts have very short max timeouts that will cause the export to fail. Using CLI takes most of
the headache away.

## Recommneded Setup

You should be running the website on a local development server for best results.

## Command-line Usage

`wp bugo <subcommand> <directory> [--<field>=<value>]`

**Subcommand**
* all - exports posts,pages and the media library.
* posts - exports only posts & pages
* media - exports the media library (preserved directories)
* originals - exports only the original media from the media library.

### Example

` # wp bugo all ~/Desktop `

Exports posts, exports posts,pages and the media library to ~/Desktop/wp-hugo-<website>

## Changelog

### 1.0

* First Release
