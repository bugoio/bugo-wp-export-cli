<?php
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
      $this->hugo_export = new Hugo_Export();
      $this->require_classes();
      $this->$fs = (object)[];
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

      private function filesystem_method_filter(){
          return 'direct';
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
          $this->fs->posts = $this->fs->export. $this->post_folder;
        }

        if($dir == 'media'){
          $this->fs->static = $this->fs->export. '/static';
          $this->fs->wpcontent = $this->fs->static . '/wp-content';
          $this->fs->uploads = $this->fs->wpcontent . '/uploads';
        }

        if($dir == 'zip'){
          $this->fs->zip = $basedir . '/wp-hugo.zip';
        }

        print_r($this->fs);

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
        return true;
      }
      

      /**
       * Export the posts
       */
      private function export_posts($args, $assoc_args) {
        WP_CLI::warning( 'Exporting posts' );
        //create necessary directories
        if($this->create_directories($args[0],'posts')){
          WP_CLI::success( 'Directories created.' );
          // copy the uploads directory to the static folder
          WP_CLI::log( "Copying the media library.\n\n" );
          $this->copy_recursive($upload_dir['basedir'], $this->fs->static . "/" . str_replace(trailingslashit(get_home_url()), '', $upload_dir['baseurl']));

        } else {
          WP_CLI::error( "Couldn't create the required directories" );
        }

        $this->create_directories($args[0],'posts');
      }

      /**
       * Export the media library
       */
      private function export_media_library($args, $assoc_args) {
        WP_CLI::log( 'Exporting media library' );
        $upload_dir = wp_upload_dir();

        //create necessary directories
        if($this->create_directories($args[0],'media')){
          $this->copy_recursive($upload_dir['basedir'], $this->fs->static . "/" . str_replace(trailingslashit(get_home_url()), '', $upload_dir['baseurl']));
        } else {
          WP_CLI::error( "Couldn't create the required directories" );
        }
      }

      /**
       * Export the media library's originals
       */
      private function export_originals($args, $assoc_args) {
        global $wpdb;
        $upload_dir = wp_upload_dir();

        WP_CLI::log( 'Exporting Original Media' );        
        //create necessary directories
        if($this->create_directories($args[0],'originals')){
          WP_CLI::success( 'Directories created.' );
          //get the images
          if($files = $this->get_originals()){
            WP_CLI::success( 'Images: ' . sizeof($files) );
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
      function posts($args, $assoc_args) {

        // give output
        WP_CLI::success( 'Exporting Posts' );

        //create necessary directories
        WP_CLI::log('Creating Directories');
        $this->create_directories('posts');
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
