<?php
/**
* Plugin Name: Bugo Export CLI
* Plugin URI: https://bugo.io/
* Description: Convert this site for use in a Hugo site. Hugo is a static site generator. <a href="https://bugo.io">Bugo Vanilla</a> is a great starter theme for Hugo. 
* Version: 1.0
* Author: Matthew Antone
* Author URI: http://matthewantone.com/
**/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
require 'vendor/autoload.php';
use League\HTMLToMarkdown\HtmlConverter;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

/**
 * Export Site for use with Hugo. This should be done on a local dev server.
 *
 * ## EXAMPLES
 *
 *     # Export all to desktop.
 *     $ wp bugo all ~/Desktop
 *     Success: Files exported to ~/Desktop/wp-hugo-<sitename>
 *
 *     # Export all original media to desktop.
 *     $ wp bugo originals ~/Desktop
 *     Success: Files exported to ~/Desktop/wp-hugo-<sitename>-originals
 *
 *     # Update option.
 *     $ wp option update my_option '{"foo": "bar"}' --format=json
 *     Success: Updated 'my_option' option.
 *
 *     # Delete option.
 *     $ wp option delete my_option
 *     Success: Deleted 'my_option' option.
 **/

  class BugoWPCLI {
      
    private $hugo_export;
    private $fs;

    public function __construct() {

      // example constructor called when plugin loads
      $this->require_classes();
      $this->$fs = (object)[];
      $this->converter = new HtmlConverter();
      
    }

    private $required_classes = array(
      'spyc' => '%pwd%/includes/spyc.php',
      'Markdownify\Parser' => '%pwd%/vendor/pixel418/markdownify/src/Parser.php',
      'Markdownify\Converter' => '%pwd%/vendor/pixel418/markdownify/src/Converter.php',
      'Markdownify\ConverterExtra' => '%pwd%/vendor/pixel418/markdownify/src/ConverterExtra.php',
      'League\HTMLToMarkdown\HtmlConverter' => '%pwd%/vendor/league/html-to-markdown/src/HtmlConverter.php'
    );

    /**
     * Copy a file, or recursively copy a folder and its contents
     *
     * @author      Aidan Lister <aidan@php.net>
     * @version     1.0.1
     * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
     *
     * @param       string $source Source path
     * @param       string $dest Destination path
     *
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    private function copy_recursive($source, $dest) {

        global $wp_filesystem;

        // Check for symlinks
        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        // Simple copy for a file
        if (is_file($source)) {
            $pathinfo = pathinfo($source);
            if(!file_exists($dest)){
              WP_CLI::log( 'Copying ' . $pathinfo['basename'] );
              return $wp_filesystem->copy($source, $dest);
            } else {
              WP_CLI::warning( $pathinfo['basename'] . "exists. Did not copy.");
              return true;
              // return $wp_filesystem->copy($source, $dest);;
            }
        }

        // Make destination directory
        if (!is_dir($dest)) {
            if (!wp_mkdir_p($dest)) {
                $wp_filesystem->mkdir($dest) or wp_die("Could not created $dest");
            }
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            $this->copy_recursive("$source/$entry", "$dest/$entry");
        }

        // Clean up
        $dir->close();
        return true;
    }

    /**
     * Determine if it's an empty value
     */
    protected function _isEmpty($value){
      if (true === is_array($value)) {
          if (true === empty($value)) {
              return true;
          }
          if (1 === count($value) && true === empty($value[0])) {
              return true;
          }
          return false;
      }
      return true === empty($value);
    }

    /**
     *  Conditionally Include required classes
     */
    private function require_classes(){

        foreach ($this->required_classes as $class => $path) {
            if (class_exists($class)) {
                continue;
            }
            $path = str_replace("%pwd%", dirname(__FILE__), $path);
            require_once($path);
        }
    }

    /**
     *  Get originals
     */
    private function get_originals(){
      global $wpdb;
      $files = $wpdb->get_results("SELECT guid FROM `wp_posts` where `post_type` = 'attachment'");
      return $files;
    }

    /**
     *  How should we handle filesystem filters
     */
    private function filesystem_method_filter(){
        return 'direct';
    }

    /**
     *  has any shortcode
     */
    private function has_any_shortcode($content){
      global $shortcode_tags;
      // print_r($shortcode_tags);
      foreach($shortcode_tags as $shortcode => $value){
        if(has_shortcode($content,$shortcode)){
            WP_CLI::log('has shortcode: ' . $shortcode );
          return true;
        }
      }
      return false;
    }

    /**
     *  strip domain
     */
    private function strip_domain($path){
      return str_replace(home_url(), '', $path);
    }

    /**
     * Create directories
     */
    private function create_directories( $basedir = './', $dir = null ){

      WP_CLI::log( 'Creating necessary directories.' );
      global $wp_filesystem;
      $site = get_bloginfo( 'name' );

      
      add_filter('filesystem_method', array(&$this, 'filesystem_method_filter'));

      WP_Filesystem();
      
      $this->fs->dir = $basedir;
      $this->fs->export = $this->fs->dir . '/wp-hugo-' . sanitize_title_with_dashes($site);

      if($dir == 'originals'){
        $this->fs->orginals = $this->fs->export . '/originals';
      }

      if($dir == 'posts'){
        $this->fs->content = $this->fs->export. '/content';
        $this->fs->posts = $this->fs->content . '/posts';
      }

      if($dir == 'media'){
        $this->fs->static = $this->fs->export. '/static';
        $this->fs->wpcontent = $this->fs->static . '/wp-content';
        $this->fs->uploads = $this->fs->wpcontent . '/uploads';
      }

      if($dir == 'zip'){
        $this->fs->zip = $basedir . '/wp-hugo.zip';
      }

      foreach($this->fs as $key => $dir){
        if(!is_dir($dir)){
          if($wp_filesystem->mkdir($dir)){
            WP_CLI::success( "Created $dir" );
          }else{
            WP_CLI::error( "Couldn't create $dir" );
            return false;
          }
        }
      }
      WP_CLI::success( 'Created necessary directories.' );
      return true;
    }

    /**
     * @param WP_Post $post
     *
     * @return bool|string
     */
    protected function _getPostDateAsIso(WP_Post $post){
        // Dates in the m/d/y or d-m-y formats are disambiguated by looking at the separator between the various components: if the separator is a slash (/),
        // then the American m/d/y is assumed; whereas if the separator is a dash (-) or a dot (.), then the European d-m-y format is assumed.
        $unixTime = strtotime($post->post_date_gmt);
        return date('c', $unixTime);
    }

    /**
     * Get an array of all post and page IDs
     * Note: We don't use core's get_posts as it doesn't scale as well on large sites
     */
    function get_posts(){

        global $wpdb;
        return $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status in ('publish', 'draft', 'private') AND post_type IN ('post', 'page' )");
    }
    
    /**
     * Convert the main post content to Markdown.
     */
    function convert_content($post){
        $content = $post->post_content;
        if($this->has_any_shortcode($content)){
          WP_CLI::log('processing shortcodes');
          $content = apply_filters('the_content', $content);
        }
        WP_CLI::success( 'Converting to Markdown');
        $markdown = $this->converter->convert($content);
        $markdown = $this->strip_domain($markdown);
        if (false !== strpos($markdown, '[]: ')) {
            // faulty links; return plain HTML
            return $content;
        }
        return $markdown;
    }
    
    function processMetaShortcodes($meta){
        $processed = apply_filters('the_content', $meta);
        $processed = $this->strip_domain($processed);
        return $this->converter->convert($processed);
    }
    
    function convertValues($acf){
      foreach($acf as $key => $value){
        if(is_array($value)){
          echo "is array\n";
          $meta =  $this->convertValues($meta);
        } else {
          $acf[$key] = $this->processMetaShortCodes($value);
        }
      }
      return $acf;
    }

    /**
     * Convert a posts meta data (both post_meta and the fields in wp_posts) to key value pairs for export
     */
    function convert_meta(WP_Post $post){
        echo "converting meta\n\n";
        $output = array(
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_XML1, 'UTF-8'),
            //'author' => get_userdata($post->post_author)->display_name,
            'type' => get_post_type($post),
            'date' => $this->_getPostDateAsIso($post),
        );
        if (false === empty($post->post_excerpt)) {
            $output['excerpt'] = $post->post_excerpt;
        }

        if (in_array($post->post_status, array('draft', 'private'))) {
            // Mark private posts as drafts as well, so they don't get
            // inadvertently published.
            $output['draft'] = true;
        }
        if ($post->post_status == 'private') {
            // hugo doesn't have the concept 'private posts' - this is just to
            // disambiguate between private posts and drafts.
            $output['private'] = true;
        }

        //turns permalink into 'url' format, since Hugo supports redirection on per-post basis
        if ('page' !== $post->post_type) {
            $output['url'] = urldecode(str_replace(home_url(), '', get_permalink($post)));
        }

        // check if the post or page has a Featured Image assigned to it.
        if (has_post_thumbnail($post)) {
            $output['images'] = [str_replace(get_site_url(), "", get_the_post_thumbnail_url($post))];
        }

        //convert ACF fields
        if(function_exists('get_fields')){
          $acf = get_fields($post);
          // print_r($acf);
          if($acf){
            $acf = $this->convertValues($acf);
            
            foreach ($acf as $key => $value) {
              if (false === $this->_isEmpty($value)) {
                $output[$key] = $value;
              }
            }
          }
        }

        //convert traditional post_meta values, hide hidden values
        $custom_post_meta = get_post_custom($post->ID);
        // if($custom_post_meta){
        //   print_r($custom_post_meta);
        //   $custom_post_meta = $this->convertValues($custom_post_meta);
        // }
        foreach ( $custom_post_meta as $key => $value) {
            if (substr($key, 0, 1) == '_') {
                continue;
            }
            // $processed = apply_filters('the_content', $value);
            if (false === $this->_isEmpty($processed)) {
              $output[$key] = $this->converter->convert($processed);
            }
        }
        return $output;
    }

    /**
     * Convert post taxonomies for export
     */
    function convert_terms($post){
        WP_CLI::log('Converting Terms');
        $output = array();
        foreach (get_taxonomies(array('object_type' => array(get_post_type($post)))) as $tax) {

            $terms = wp_get_post_terms($post, $tax);

            //convert tax name for Hugo
            switch ($tax) {
                case 'post_tag':
                    $tax = 'tags';
                    break;
                case 'category':
                    $tax = 'categories';
                    break;
            }

            if ($tax == 'post_format') {
                $output['format'] = get_post_format($post);
            } else {
                $output[$tax] = wp_list_pluck($terms, 'name');
            }
        }
        WP_CLI::success( 'Done Converting Terms' );
    }

    /**
     * Write file to temp dir
     */
    function write($output, $post){

        global $wp_filesystem;

        if (get_post_type($post) == 'page') {
            $filepath = parse_url(get_the_permalink($post));
            $relpath = str_replace($post->post_name, '', substr($filepath['path'], 0, -1));
                
            switch(true){
              // home page
              case get_option( 'page_for_posts' ) == $post->ID:
                $filename = $this->fs->posts . '/_index.md';
                break;
              // blog page
              case get_option( 'page_on_front' ) == $post->ID:
                $filename = $this->fs->content . '/_index.md';
                break;
              // catch the ones that fall through the cracks
              case !$relpath:
                $filename = $this->fs->content . '/' . sanitize_title_with_dashes($post->post_title) . '.md';
                break;
              // everyone else
              default:
                $curDir = $this->fs->content . $relpath;
                $wp_filesystem->mkdir($curDir);
                $filename = $curDir . sanitize_title_with_dashes($post->post_title) . '.md';
                break;
            }
        } else {
            $filename = $this->fs->posts . '/' . sanitize_title_with_dashes($post->post_title) . '.md';
        }

        $wp_filesystem->put_contents($filename, $output);
    }


      /**
       * Export the posts
       */
      private function export_posts($args, $assoc_args) {
        WP_CLI::warning( 'Exporting posts' );
        //create necessary directories
        if($this->create_directories($args[0],'posts')){
          //convert posts for hugo
          global $post;

          foreach ($this->get_posts() as $postID) {
              WP_CLI::log('Converting ' . get_the_title($postID) );
              $post = get_post($postID);
              setup_postdata($post);
              $meta = $this->convert_meta($post);
              $terms = $this->convert_terms($postID);
              if($terms){
                $meta = array_merge( $meta, $terms );
              }

              // remove falsy values, which just add clutter
              foreach ($meta as $key => $value) {
                  if (!is_numeric($value) && !$value) {
                      unset($meta[$key]);
                  }
              }

              // Hugo doesn't like word-wrapped permalinks
              $output = Spyc::YAMLDump($meta, false, 0);

              $output .= "\n---\n";
              WP_CLI::log('Processing content.');
              $output .= $this->convert_content($post);
              WP_CLI::log('Including Comments');
              if ($this->include_comments) {
                  $output .= $this->convert_comments($post);
              }
              $this->write($output, $post);
              WP_CLI::success('Converted ' . get_the_title($postID) );
          }
          WP_CLI::success( 'Done exporting posts.' );
        } else {
          WP_CLI::error( "Couldn't create the required directories" );
        }
      }

      /**
       * Export the media library
       */
      private function export_media_library($args, $assoc_args) {
        WP_CLI::log( 'Exporting media library.' );
        $upload_dir = wp_upload_dir();

        //create necessary directories
        if($this->create_directories($args[0],'media')){
          $this->copy_recursive($upload_dir['basedir'], $this->fs->static . "/" . str_replace(trailingslashit(get_home_url()), '', $upload_dir['baseurl']));
        } else {
          WP_CLI::error( "Couldn't create the required directories" );
        }
        WP_CLI::success( 'Done Exporting media library.' );
      }

      /**
       * Export the media library's originals
       */
      private function export_originals($args, $assoc_args) {
        global $wpdb;
        $upload_dir = wp_upload_dir();

        WP_CLI::log( 'Exporting Original Media.' );        
        //create necessary directories
        if($this->create_directories($args[0],'originals')){
          //get the images
          if($files = $this->get_originals()){
            WP_CLI::success( 'Images: ' . sizeof($files) . '.');
            //copy the images
            foreach($files as $file){
              $url_info = parse_url($file->guid);
              $path_info = pathinfo($url_info['path']);
              $file_path = str_replace('/wp-content/uploads',"",$url_info['path']);
              $src = $upload_dir['basedir'] . $file_path;
              $target = $this->fs->orginals . "/" . $path_info['basename'];
              if(!file_exists($target)){
                if(copy($src,$target)){
                  WP_CLI::success( 'Copied: ' . $path_info['basename'] );
                } else {
                  WP_CLI::error( 'Copy Failed: ' . $path_info['basename'] );
                }
              } else {
                WP_CLI::warning( $path_info['basename'] . " already exists." );
                $targetinfo = pathinfo( $target );
                $target = $targetinfo['dirname'] . '/' . $targetinfo['filename'] . '-' . md5(time()) . '.' . $targetinfo['extension'];
                if(copy($src,$target)){
                  WP_CLI::success( 'Duplicated: ' . $target );
                }else{
                  WP_CLI::error( 'Copy Failed: ' . $target );
                }
              }
            }
          }else{
            WP_CLI::error( "Didn't recieve any images" );
          }
          
        }else{
          WP_CLI::error( "Couldn't create the required directories" );
        }
         WP_CLI::success( 'Done exporting media library originals.' );
      }

      /**
       * Exports The original media from this site's media library.
       *
       * ## OPTIONS
       *
       * <directory>
       * : The path where you want to export the files.
       * 
       * [--<field>=<value>]
       * ---
       * default: success
       * options:
       *   - success
       *   - error
       * ---
       *
       * ## EXAMPLES
       *
       *     wp example hello Newman
       *
       * 
       */      
      public function originals($args, $assoc_args) {
        $this->export_originals($args, $assoc_args);
      }

      /**
       * Exports this site's posts.
       *
       * ## OPTIONS
       *
       * <directory>
       * : The path where you want to export the files.
       * [--<field>=<value>]
       *
       * ## EXAMPLES
       *
       *     wp example hello Newman
       *
       * @when after_wp_load
       */      
      function posts($args, $assoc_args) {
        $this->export_posts($args, $assoc_args);  
      }
      

      /**
       * Exports this site's media library for use with Hugo.
       *
       * ## OPTIONS
       *
       * <directory>
       * : The path where you want to export the files.
       *
       * [--<field>=<value>]
       * ---
       * default: success
       * options:
       *   - success
       *   - error
       * ---
       *
       * ## EXAMPLES
       *
       *     wp example hello Newman
       *
       * @when after_wp_load
       */      
      public function media( $args, $assoc_args ) {

        WP_CLI::success( 'Exporting Media Library' );
        //create necessary directories
        WP_CLI::log('Creating Directories');
        $this->export_media_library( $args, $assoc_args );
      }

      /**
       * Exports this site's posts, media library and originals.
       *
       * ## OPTIONS
       *
       * <directory>
       * : The path where you want to export the files.
       * default: ~/
       * [--<field>=<value>]
       * : someting
       * ---
       * 
       * ---
       *
       * ## EXAMPLES
       *
       *     wp all ~/Desktop
       *
       * 
       */      
      public function all($args, $assoc_args) {
        $exportDir = $args[0];
        $exportName = WP_CLI\Utils\get_flag_value($assoc_args, 'exportName', $default = "wp-export-".sanitize_title_with_dashes(get_bloginfo('name')));
        $assoc_args['exportName'] = $exportName;

        if(is_dir($args[0])){
          WP_CLI::success( "Valid directory. Exporting all to {$args[0]}{$exportName}" );
          // WP_CLI\Utils\format_items( 'table', $this , 'yaml');
          // $response = WP_CLI::run_command( ['bugo','pages',$args[0]], $assoc_args, true, true );
        }else {
          WP_CLI::error('You must supply a valid directory');
        }
        $this->export_originals($args, $assoc_args);
        $this->export_posts($args, $assoc_args);
        $this->export_media_library($args, $assoc_args);
      }

  }

  WP_CLI::add_command( 'bugo', 'BugoWPCLI' );

}
