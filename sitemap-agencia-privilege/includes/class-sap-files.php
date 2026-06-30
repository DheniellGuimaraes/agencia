<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SAP_Files {
	public static function upload_info() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'sitemap-agencia-privilege';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'sitemap-agencia-privilege';
		return array( 'dir' => $dir, 'url' => $url );
	}

	public static function ensure_directory() {
		$info = self::upload_info();
		if ( ! is_dir( $info['dir'] ) ) {
			wp_mkdir_p( $info['dir'] );
		}
		if ( is_dir( $info['dir'] ) && ! file_exists( trailingslashit( $info['dir'] ) . 'index.php' ) ) {
			file_put_contents( trailingslashit( $info['dir'] ) . 'index.php', "<?php\n// Silence is golden.\n" );
		}
		return is_dir( $info['dir'] ) && is_writable( $info['dir'] );
	}

	public static function main_url() {
		return self::can_mirror_to_root() ? home_url( '/sitemap.xml' ) : self::uploads_main_url();
	}

	public static function uploads_main_url() {
		$info = self::upload_info();
		return trailingslashit( $info['url'] ) . 'sitemap.xml';
	}

	public static function root_info() {
		return array(
			'dir' => untrailingslashit( ABSPATH ),
			'url' => home_url( '/' ),
		);
	}

	public static function can_mirror_to_root() {
		$root = self::root_info();
		return is_dir( $root['dir'] ) && is_writable( $root['dir'] );
	}

	public static function safe_path( $filename ) {
		$info = self::upload_info();
		return self::safe_path_in_dir( $filename, $info['dir'] );
	}

	public static function safe_root_path( $filename ) {
		$root = self::root_info();
		return self::safe_path_in_dir( $filename, $root['dir'] );
	}

	private static function safe_path_in_dir( $filename, $directory ) {
		$filename = sanitize_file_name( $filename );
		$path     = trailingslashit( $directory ) . $filename;
		$base     = realpath( $directory );
		$real_dir = realpath( dirname( $path ) );
		if ( ! $base || ! $real_dir || 0 !== strpos( $real_dir, $base ) || ! preg_match( '/^sitemap[-a-z0-9_]*\.xml$/', $filename ) ) {
			return false;
		}
		return $path;
	}

	public static function mirror_to_root( $filename ) {
		if ( ! self::can_mirror_to_root() ) {
			return false;
		}

		$source = self::safe_path( $filename );
		$target = self::safe_root_path( $filename );
		if ( ! $source || ! $target || ! file_exists( $source ) ) {
			return false;
		}

		return copy( $source, $target );
	}

	public static function delete_generated() {
		self::ensure_directory();
		$info  = self::upload_info();
		$count = 0;
		foreach ( glob( trailingslashit( $info['dir'] ) . 'sitemap*.xml' ) as $file ) {
			if ( is_file( $file ) && 0 === strpos( realpath( $file ), realpath( $info['dir'] ) ) ) {
				$count += wp_delete_file( $file ) ? 1 : 0;
			}
		}

		$root = self::root_info();
		if ( self::can_mirror_to_root() ) {
			foreach ( glob( trailingslashit( $root['dir'] ) . 'sitemap*.xml' ) as $file ) {
				if ( is_file( $file ) && 0 === strpos( realpath( $file ), realpath( $root['dir'] ) ) ) {
					$count += wp_delete_file( $file ) ? 1 : 0;
				}
			}
		}
		return $count;
	}

	public static function list_files() {
		self::ensure_directory();
		$info     = self::upload_info();
		$url_base = self::can_mirror_to_root() ? home_url( '/' ) : $info['url'];
		$files    = array();
		foreach ( glob( trailingslashit( $info['dir'] ) . 'sitemap*.xml' ) as $file ) {
			$name = basename( $file );
			$files[] = array(
				'name'    => $name,
				'url'     => trailingslashit( $url_base ) . rawurlencode( $name ),
				'size'    => size_format( filesize( $file ) ),
				'created' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $file ) ),
				'urls'    => self::count_urls( $file ),
			);
		}
		return $files;
	}

	public static function count_urls( $file ) {
		$content = file_get_contents( $file, false, null, 0, 1024 * 1024 );
		return $content ? substr_count( $content, '<url>' ) : 0;
	}

	public static function diagnostics() {
		$writable = self::ensure_directory();
		$info     = self::upload_info();
		$files    = self::list_files();
		return array(
			'directory' => $info['dir'],
			'exists'    => is_dir( $info['dir'] ),
			'writable'  => $writable,
			'files'     => count( $files ),
			'main_url'  => self::main_url(),
		);
	}
}
