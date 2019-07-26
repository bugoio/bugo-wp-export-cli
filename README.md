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

## Why CLI?

It's been my experience that it just runs faster. Server speeds and setup can vary wildly. 
Some hosts have very short max timeouts that will cause the export to fail. Using CLI takes most of
the headache away.

## Recommneded Setup

You should be running the website on a local WordPress installation.

## Usage with a locally hosted WordPress installation

1. Download this repo as a zip file in the upper right hand corner of this page.
1. Place plugin zip in `/wp-content/plugins/` folder
2. Unzip the file
1. Activate plugin in WordPress dashboard
1. In a termainal navigate to your WordPress installation to begin using WP-CLI
1. Type in your command. See **Command-line Usage** below.

## Command-line Usage

`wp bugo <subcommand> <directory>`

**Subcommand**
* export - export pages, posts and media library
* posts - exports only posts & pages
* media - exports the media library (preserved directories)
* originals - exports only the original media from the media library
* all - exports posts,pages and the media library

**Directory**
This is your target directory. A new folder containing the export will be created here.

### Example

` # wp bugo all ~/Desktop `

Exports posts, exports posts,pages and the media library to `~/Desktop/wp-hugo-<website>`

## Changelog

### 1.0

* First Release
