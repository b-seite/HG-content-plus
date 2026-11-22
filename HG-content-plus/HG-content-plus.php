<?php
/**
 * Plugin Name: HG Content Plus
 * Description: Blendet über Bildern, deren Container die Klasse "mpii" trägt, einen runden "+-Button ein, der einen dem Bild zugeordneten, formatierten Text zeigt (Text pro Bild in der Mediathek, Editor auf der Datei-Detailseite). Optik unter Einstellungen → Content Plus. Updates erfolgen automatisch aus dem GitHub-Repo HG-content-plus.
 * Version: 3.2.1
 * Author: Boris Beplate
 * License: GPL-2.0-or-later
 * Text Domain: hg-content-plus
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

final class HG_Content_Plus {

	/* Meta-Key und Options-Key bewusst unverändert (Alt-Daten von mpi-bild-info bleiben erhalten). */
	const META_KEY   = 'mpii_info';
	const CLASS_NAME = 'mpii';
	const OPTION_KEY = 'mpii_settings';
	const NONCE      = 'mpii_editor_nonce';

	/** GitHub-Repo für automatische Updates (über Filter hgcp_github_repo änderbar). */
	public static function github_repo() {
		return apply_filters( 'hgcp_github_repo', 'b-seite/HG-content-plus' );
	}

	/** Standardwerte für die Einstellungen. */
	public static function defaults() {
		return [
			'btn_size'   => '50',
			'btn_side'   => '20',
			'btn_bottom' => '20',
			'btn_bg'     => 'rgba(218,218,218,0.5)',
			'box_bg'     => 'rgba(255,255,255,0.9)',
			'side_pos'   => 'rechts',
			'vert_pos'   => 'unten',
			'off_x'      => '0',
			'off_y'      => '0',
			'fade'       => '500',
			'plus_width' => '10',
			'plus_len'   => '56',
		];
	}

	public static function init() {
		/* Backend: Feld "Bild-Infotext" in der Mediathek (Modal). */
		add_filter( 'attachment_fields_to_edit', [ __CLASS__, 'field_edit' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ __CLASS__, 'field_save' ], 10, 2 );

		/* Backend: Format-Editor (TinyMCE) auf der Datei-Detailseite des Bildes. */
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_editor_metabox' ] );
		add_action( 'edit_attachment', [ __CLASS__, 'save_editor_metabox' ] );

		/* Einstellungsseite: Einstellungen → Content Plus. */
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		/* Automatische Updates aus dem GitHub-Repo. */
		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_github_update' ] );

		/* Frontend. */
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

		/* Ajax: Text zu einer Bild-URL liefern (für Gäste und Eingeloggte). */
		add_action( 'wp_ajax_mpii_get', [ __CLASS__, 'ajax_get' ] );
		add_action( 'wp_ajax_nopriv_mpii_get', [ __CLASS__, 'ajax_get' ] );

		/* Ajax: Infotexte für ALLE Bilder einer Seite in einem Sammel-Request. */
		add_action( 'wp_ajax_mpii_get_batch', [ __CLASS__, 'ajax_get_batch' ] );
		add_action( 'wp_ajax_nopriv_mpii_get_batch', [ __CLASS__, 'ajax_get_batch' ] );
	}

	private static function settings() {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return array_merge( self::defaults(), $saved );
	}

	/* ---------- Backend: Mediathek (Modal, Listenansicht) ---------- */

	/** Textarea "Bild-Infotext" im Mediendialog. Auf der Datei-Detailseite steht stattdessen der Format-Editor. */
	public static function field_edit( $form_fields, $post ) {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'attachment' === $screen->id ) {
				return $form_fields;
			}
		}
		$form_fields[ self::META_KEY ] = [
			'label' => 'Bild-Infotext',
			'input' => 'textarea',
			'value' => (string) get_post_meta( $post->ID, self::META_KEY, true ),
			'helps' => 'Wird über dem Bild per "+-Button eingeblendet (Container-Klasse "' . self::CLASS_NAME . '"). Für bequeme Formatierung: Datei öffnen (Datei-Detailseite) – dort gibt es einen kleinen Editor.',
		];
		return $form_fields;
	}

	public static function field_save( $post, $attachment ) {
		if ( isset( $attachment[ self::META_KEY ] ) ) {
			update_post_meta( $post['ID'], self::META_KEY, wp_kses_post( $attachment[ self::META_KEY ] ) );
		}
		return $post;
	}

	/* ---------- Backend: TinyMCE-Editor auf der Datei-Detailseite ---------- */

	public static function add_editor_metabox() {
		add_meta_box(
			'mpii_editor',
			__( 'Bild-Infotext (Content Plus "+")', 'hg-content-plus' ),
			[ __CLASS__, 'render_editor_metabox' ],
			'attachment',
			'normal',
			'high'
		);
	}

	public static function render_editor_metabox( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );

		wp_editor(
			(string) get_post_meta( $post->ID, self::META_KEY, true ),
			'mpii_info_editor',
			[
				'textarea_name' => 'mpii_info_editor',
				'textarea_rows' => 7,
				'media_buttons' => false,
				'teeny'         => false,
				'tinymce'       => [
					'toolbar1'  => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo,removeformat',
					'toolbar2'  => '',
					'statusbar' => false,
					'resize'    => true,
				],
				'quicktags'     => [ 'buttons' => 'strong,em,link,ul,ol,li,code' ],
			]
		);
		echo '<p class="description">' . esc_html__( 'Formatierter Text, der per "+-Button über dem Bild erscheint. Erscheint nur bei Bildern in Containern mit der Klasse "mpii".', 'hg-content-plus' ) . '</p>';
	}

	public static function save_editor_metabox( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$value = isset( $_POST['mpii_info_editor'] ) ? wp_kses_post( wp_unslash( $_POST['mpii_info_editor'] ) ) : '';
		update_post_meta( $post_id, self::META_KEY, $value );
	}

	/* ---------- Backend: Einstellungsseite ---------- */

	public static function add_settings_page() {
		add_options_page(
			__( 'Content Plus', 'hg-content-plus' ),
			__( 'Content Plus', 'hg-content-plus' ),
			'manage_options',
			'mpii-settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	public static function register_settings() {
		register_setting(
			'mpii_settings_group',
			self::OPTION_KEY,
			[ 'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ] ]
		);
	}

	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$out      = [];
		$in       = is_array( $input ) ? $input : [];

		/* Auswahlwerte. */
		$out['side_pos'] = ( isset( $in['side_pos'] ) && 'links' === $in['side_pos'] ) ? 'links' : 'rechts';
		$out['vert_pos'] = ( isset( $in['vert_pos'] ) && 'oben' === $in['vert_pos'] ) ? 'oben' : 'unten';

		/* Zahlenwerte (px). */
		foreach ( [ 'btn_size', 'btn_side', 'btn_bottom', 'off_x', 'off_y', 'plus_width', 'plus_len' ] as $key ) {
			$number = isset( $in[ $key ] ) ? (int) $in[ $key ] : (int) $defaults[ $key ];
			$min    = ( 'btn_size' === $key ) ? 24 : -500;
			$max    = 500;
			if ( 'btn_size' === $key ) {
				$max = 200;
			}
			if ( 'btn_side' === $key || 'btn_bottom' === $key ) {
				$min = 0;
				$max = 200;
			}
			if ( 'plus_width' === $key || 'plus_len' === $key ) {
				$min = 1;
				$max = 80;
			}
			$out[ $key ] = (string) max( $min, min( $max, $number ) );
		}

		/* Fadezeit (ms). */
		$fade        = isset( $in['fade'] ) ? (int) $in['fade'] : (int) $defaults['fade'];
		$out['fade'] = (string) max( 100, min( 3000, $fade ) );

		/* Farben: Hex oder rgba(). */
		foreach ( [ 'btn_bg', 'box_bg' ] as $key ) {
			$color = isset( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : $defaults[ $key ];
			if ( preg_match( '/^#([0-9a-fA-F]{3,8})$/', $color ) || preg_match( '/^rgba?\([^)]+\)$/', $color ) ) {
				$out[ $key ] = $color;
			} else {
				$out[ $key ] = $defaults[ $key ];
			}
		}

		return $out;
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = self::settings();
		$side    = $s['side_pos'];
		$vert    = $s['vert_pos'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content Plus (Bild-Infotext mit "+")', 'hg-content-plus' ); ?></h1>
			<p>
				<?php esc_html_e( 'Hier stellst du Aussehen und Verhalten ein. Der Text selbst wird pro Bild in der Mediathek gepflegt (Dateianzeige "Bild-Infotext"), die Klasse "mpii" im Tatsu-/Oshine-Modul aktiviert das Feature.', 'hg-content-plus' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'mpii_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Buttongröße', 'hg-content-plus' ); ?></th>
						<td>
							<input type="number" min="24" max="200" step="1" id="mpii_btn_size"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[btn_size]"
								value="<?php echo esc_attr( $s['btn_size'] ); ?>"> px
							<p class="description"><?php esc_html_e( 'Durchmesser des runden "+-Buttons. Schriftgröße passt sich automatisch an.', 'hg-content-plus' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_plus_width"><?php esc_html_e( 'Plus: Balkendicke', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="1" max="80" step="1" id="mpii_plus_width"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[plus_width]"
							value="<?php echo esc_attr( $s['plus_width'] ); ?>"> %
							<p class="description"><?php esc_html_e( 'Dicke (Strichstärke) der zwei Kreuzbalken relativ zum Buttondurchmesser. Standard 10 (= klassisches Plus).', 'hg-content-plus' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_plus_len"><?php esc_html_e( 'Plus: Balkenlänge', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="1" max="80" step="1" id="mpii_plus_len"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[plus_len]"
							value="<?php echo esc_attr( $s['plus_len'] ); ?>"> %
							<p class="description"><?php esc_html_e( 'Länge der Balken relativ zum Buttondurchmesser (Standard 56 = 56 % = klassisches Plus). Skaliert automatisch mit der Buttongröße.', 'hg-content-plus' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Button: Position (Seite)', 'hg-content-plus' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[side_pos]" value="rechts" <?php checked( $side, 'rechts' ); ?>> rechts</label>
							&nbsp;&nbsp;
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[side_pos]" value="links" <?php checked( $side, 'links' ); ?>> links</label>
							<p class="description"><?php esc_html_e( 'Auf welcher Seite des Bildes der "+" sitzt. Das Popup öffnet automatisch auf der gegenüberliegenden Seite.', 'hg-content-plus' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Button: Position (Höhe)', 'hg-content-plus' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[vert_pos]" value="unten" <?php checked( $vert, 'unten' ); ?>> unten</label>
							&nbsp;&nbsp;
							<label><input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[vert_pos]" value="oben" <?php checked( $vert, 'oben' ); ?>> oben</label>
							<p class="description"><?php esc_html_e( 'Oben oder unten im Bild – das Popup öffnet automatisch darunter bzw. darüber.', 'hg-content-plus' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_btn_side"><?php esc_html_e( 'Button: Abstand zur Seite', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="0" max="200" step="1" id="mpii_btn_side"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[btn_side]"
							value="<?php echo esc_attr( $s['btn_side'] ); ?>"> px</td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_btn_bottom"><?php esc_html_e( 'Button: Abstand oben/unten', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="0" max="200" step="1" id="mpii_btn_bottom"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[btn_bottom]"
							value="<?php echo esc_attr( $s['btn_bottom'] ); ?>"> px</td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_off_x"><?php esc_html_e( 'Popup: horizontaler Offset', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="-500" max="500" step="1" id="mpii_off_x"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[off_x]"
							value="<?php echo esc_attr( $s['off_x'] ); ?>"> px
						<p class="description"><?php esc_html_e( 'Verschiebt das Popup ausgehend vom Mittelpunkt des "+ (0 = Kante des Popups am Mittelpunkt, negative Werte nach links, positive nach rechts).', 'hg-content-plus' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_off_y"><?php esc_html_e( 'Popup: vertikaler Offset', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="-500" max="500" step="1" id="mpii_off_y"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[off_y]"
							value="<?php echo esc_attr( $s['off_y'] ); ?>"> px
						<p class="description"><?php esc_html_e( 'Analog vertikal (negativ = nach oben, positiv = nach unten).', 'hg-content-plus' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_fade"><?php esc_html_e( 'Fadezeit', 'hg-content-plus' ); ?></label></th>
						<td><input type="number" min="100" max="3000" step="50" id="mpii_fade"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fade]"
							value="<?php echo esc_attr( $s['fade'] ); ?>"> ms</td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_btn_bg"><?php esc_html_e( 'Button: Hintergrundfarbe', 'hg-content-plus' ); ?></label></th>
						<td><input type="text" class="regular-text" id="mpii_btn_bg"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[btn_bg]"
							value="<?php echo esc_attr( $s['btn_bg'] ); ?>">
						<p class="description"><?php esc_html_e( 'Hex (#fff) oder rgba(...), z. B. rgba(218,218,218,0.5).', 'hg-content-plus' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="mpii_box_bg"><?php esc_html_e( 'Popup: Hintergrundfarbe', 'hg-content-plus' ); ?></label></th>
						<td><input type="text" class="regular-text" id="mpii_box_bg"
							name="<?php echo esc_attr( self::OPTION_KEY ); ?>[box_bg]"
							value="<?php echo esc_attr( $s['box_bg'] ); ?>"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ---------- GitHub-Updates ---------- */

	/** Prüft das neueste GitHub-Release und meldet es WordPress als available Update. */
	public static function check_github_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$repo    = self::github_repo();
		$release = get_transient( 'hgcp_github_release' );

		if ( false === $release ) {
			$response = wp_remote_get(
				'https://api.github.com/repos/' . rawurlencode( $repo ) . '/releases/latest',
				[
					'timeout' => 5,
					'headers' => [ 'Accept' => 'application/vnd.github+json' ],
				]
			);
			$release  = [];
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $data ) && ! empty( $data['tag_name'] ) && ! empty( $data['zip_download_url'] ) ) {
					/* tag_name muss der Plugin-Version entsprechen, z. B. v3.1.0 -> 3.1.0. */
					$version = ltrim( (string) $data['tag_name'], 'vV' );
					$assets  = isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : [];
					$zip     = '';
					foreach ( $assets as $asset ) {
						if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
							$zip = $asset['browser_download_url'];
							break;
						}
					}
					if ( '' !== $zip && '' !== $version ) {
						$release = [ 'version' => $version, 'zip' => $zip ];
					}
				}
			}
			set_transient( 'hgcp_github_release', $release, 6 * HOUR_IN_SECONDS );
		}

		if ( empty( $release['version'] ) || empty( $release['zip'] ) ) {
			return $transient;
		}

		$basename = plugin_basename( __FILE__ );
		$current  = isset( $transient->checked[ $basename ] ) ? $transient->checked[ $basename ] : null;
		if ( null === $current || version_compare( $release['version'], $current, '<=' ) ) {
			return $transient;
		}

		$obj                              = new stdClass();
		$obj->slug                        = dirname( $basename );
		$obj->plugin                      = $basename;
		$obj->new_version                 = $release['version'];
		$obj->url                         = 'https://github.com/' . $repo;
		$obj->package                     = $release['zip'];
		$transient->response[ $basename ] = $obj;

		return $transient;
	}

	/* ---------- Frontend ---------- */

	public static function enqueue() {
		$s = self::settings();

		wp_register_style( 'hgcp', false, [], '3.2.1' );
		wp_enqueue_style( 'hgcp' );
		wp_add_inline_style( 'hgcp', self::css( $s ) );

		wp_enqueue_script( 'hgcp', plugins_url( 'hgcp.js', __FILE__ ), [], '3.2.1', true );
		wp_localize_script( 'hgcp', 'HGCPlus', [
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'mpii_get' ),
			'sidePos'  => $s['side_pos'],
			'vertPos'  => $s['vert_pos'],
			'distSide' => (float) $s['btn_side'],
			'distVert' => (float) $s['btn_bottom'],
			'size'     => (float) $s['btn_size'],
			'offX'     => (float) $s['off_x'],
			'offY'     => (float) $s['off_y'],
			'fade'     => (int) $s['fade'],
			'plusWidth' => max( 1, min( 80, (int) $s['plus_width'] ) ),
			'plusLen'   => max( 1, min( 80, (int) $s['plus_len'] ) ),
		] );
	}

	private static function css( $s ) {
		$size   = (float) $s['btn_size'];
		$font   = round( $size * 0.84, 1 );
		$btn_bg = $s['btn_bg'];
		$box_bg = $s['box_bg'];
		$fade   = (int) $s['fade'];

		return <<<CSS
.mpii{position:relative;display:block}
.mpii-host{position:relative;display:block}
.mpii .mpii-toggle{position:absolute;width:{$size}px;height:{$size}px;border-radius:50%;background:{$btn_bg};border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;z-index:2;transition:background .25s ease}
.mpii .mpii-toggle:hover{filter:brightness(.92)}
.mpii .mpii-toggle svg{width:54%;height:54%;display:block}
.mpii .mpii-box{position:absolute;padding:10px 20px;line-height:1.8;background:{$box_bg};z-index:3;box-shadow:0 8px 20px rgba(0,0,0,.15);opacity:0;transition:opacity {$fade}ms ease;max-width:calc(100% - 8px)}
.mpii .mpii-box.mpii-open{opacity:1}
.mpii .mpii-box p:first-child{margin-top:0}
.mpii .mpii-box p:last-child{margin-bottom:0}
CSS;
	}

	/* ---------- Ajax ---------- */

	/** Ausgabe des Infotexts: wpautop macht aus blossen Zeilenumbruechen echte <p>/<br>-Elemente. */
	public static function render_info( $attachment_id ) {
		$raw = (string) get_post_meta( $attachment_id, self::META_KEY, true );
		return wpautop( $raw );
	}

	/** Findet zu einer Bild-URL den Attachment-Post und liefert den gespeicherten Infotext. */
	public static function ajax_get() {
		check_ajax_referer( 'mpii_get', 'nonce' );

		$src = isset( $_POST['src'] ) ? esc_url_raw( wp_unslash( $_POST['src'] ) ) : '';
		if ( '' === $src ) {
			wp_send_json_error( 'no src', 400 );
		}

		$attachment_id = self::attachment_id_by_url( $src );
		$html          = $attachment_id ? self::render_info( (int) $attachment_id ) : '';

		wp_send_json_success( [ 'html' => $html ] );
	}

	/** Sammel-Endpunkt: liefert die Infotexte zu vielen Bild-URLs auf einmal (Map src => html). */
	public static function ajax_get_batch() {
		check_ajax_referer( 'mpii_get', 'nonce' );

		$srcs = [];
		if ( isset( $_POST['srcs'] ) && is_array( $_POST['srcs'] ) ) {
			$srcs = array_map( 'esc_url_raw', (array) wp_unslash( $_POST['srcs'] ) );
		}
		$srcs = array_slice( array_unique( array_filter( $srcs ) ), 0, 300 );

		$map = [];
		foreach ( $srcs as $src ) {
			$attachment_id = self::attachment_id_by_url( $src );
			$map[ $src ]   = $attachment_id ? self::render_info( (int) $attachment_id ) : '';
		}

		wp_send_json_success( [ 'map' => $map ] );
	}

	/** URL -> Attachment-ID. Berücksichtigt Größen-Suffixe (-1024x768), -scaled und CDN-URLs (Optimole). */
	private static function attachment_id_by_url( $url ) {
		$upload_dir = wp_get_upload_dir();
		$base       = $upload_dir['baseurl'];

		$path = $url;
		if ( false !== strpos( $url, $base ) ) {
			$path = substr( $url, strlen( $base ) );
		} else {
			$path = str_replace( trailingslashit( dirname( $base ) ), '', $url );
		}

		/* Größen-Suffix entfernen: name-1024x768.jpg -> name.jpg */
		$clean = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/', '', $path );

		$candidates = array_values( array_unique( array_filter( [ ltrim( $clean, '/' ), ltrim( $path, '/' ) ] ) ) );

		foreach ( $candidates as $file ) {
			$posts = get_posts( [
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[ 'key' => '_wp_attached_file', 'value' => $file ],
				],
			] );
			if ( ! empty( $posts ) ) {
				return (int) $posts[0];
			}
		}

		/* Fallback: Dateiname enthält. */
		$posts = get_posts( [
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'fields'      => 'ids',
			'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[ 'key' => '_wp_attached_file', 'value' => basename( $clean ), 'compare' => 'LIKE' ],
			],
		] );
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}
}

HG_Content_Plus::init();
