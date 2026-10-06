<?php
/**
 * Plugin Name: Ziyaretçi Sohbet (Telegram)
 * Description: Site ziyaretçileriyle Telegram üzerinden, mesajı yanıtlayarak tek pencerede birden fazla ziyaretçiyle sohbet edin. Erişilebilirlik ve ekran okuyucu uyumludur.
 * Version: 1.0.0
 * Text Domain: ziyaretci-sohbet
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZS_VERSION', '1.0.0' );
define( 'ZS_URL', plugin_dir_url( __FILE__ ) );

function zs_table() {
	global $wpdb;
	return $wpdb->prefix . 'zs_messages';
}

function zs_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . zs_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session varchar(32) NOT NULL,
		direction varchar(8) NOT NULL,
		message text NOT NULL,
		tg_message_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY session (session),
		KEY tg_message_id (tg_message_id)
	) $charset;" );
}
register_activation_hook( __FILE__, 'zs_activate' );

function zs_get_options() {
	return wp_parse_args( get_option( 'zs_options', array() ), array(
		'bot_token' => '',
		'chat_id'   => '',
		'secret'    => '',
	) );
}

function zs_is_configured() {
	$o = zs_get_options();
	return '' !== $o['bot_token'] && '' !== $o['chat_id'];
}

/* ---------- Telegram ---------- */

function zs_telegram( $method, $params, $token = '' ) {
	if ( '' === $token ) {
		$o     = zs_get_options();
		$token = $o['bot_token'];
	}
	$r = wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/' . $method, array(
		'timeout' => 15,
		'body'    => $params,
	) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$data = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( empty( $data['ok'] ) ) {
		return new WP_Error( 'zs_telegram', isset( $data['description'] ) ? $data['description'] : 'Telegram hatası' );
	}
	return $data['result'];
}

/* ---------- Ayarlar ---------- */

function zs_sanitize_options( $input ) {
	$old   = zs_get_options();
	$token = isset( $input['bot_token'] ) ? trim( sanitize_text_field( $input['bot_token'] ) ) : '';
	$chat  = isset( $input['chat_id'] ) ? trim( sanitize_text_field( $input['chat_id'] ) ) : '';

	if ( '' === $token || ! preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $token ) ) {
		add_settings_error( 'zs_options', 'zs_token', 'Bot token zorunludur ve geçerli biçimde olmalıdır (örn. 123456:ABC-DEF).' );
		return $old;
	}
	if ( '' === $chat || ! preg_match( '/^-?\d+$/', $chat ) ) {
		add_settings_error( 'zs_options', 'zs_chat', 'Chat ID zorunludur ve sayısal olmalıdır.' );
		return $old;
	}

	$secret = $old['secret'] ? $old['secret'] : wp_generate_password( 32, false );
	$new    = array( 'bot_token' => $token, 'chat_id' => $chat, 'secret' => $secret );

	$res = zs_telegram( 'setWebhook', array(
		'url'          => rest_url( 'zs/v1/telegram' ),
		'secret_token' => $secret,
	), $token );
	if ( is_wp_error( $res ) ) {
		add_settings_error( 'zs_options', 'zs_hook', 'Telegram webhook ayarlanamadı: ' . esc_html( $res->get_error_message() ) . ' (Siteniz HTTPS olmalıdır.)' );
		return $old;
	}
	add_settings_error( 'zs_options', 'zs_ok', 'Ayarlar kaydedildi ve Telegram bağlantısı kuruldu.', 'updated' );
	return $new;
}

add_action( 'admin_init', function () {
	register_setting( 'zs_group', 'zs_options', array( 'sanitize_callback' => 'zs_sanitize_options' ) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Ziyaretçi Sohbet', 'Ziyaretçi Sohbet', 'manage_options', 'ziyaretci-sohbet', 'zs_settings_page' );
} );

function zs_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = zs_get_options();
	?>
	<div class="wrap">
		<h1>Ziyaretçi Sohbet Ayarları</h1>
		<?php settings_errors( 'zs_options' ); ?>
		<p>Ziyaretçi mesajları Telegram'a iletilir. Bir ziyaretçiye cevap vermek için Telegram'da ilgili mesaja <strong>yanıtla (reply)</strong> özelliğini kullanın.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'zs_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="zs_bot_token">Bot Token <span aria-hidden="true">*</span></label></th>
					<td>
						<input type="password" id="zs_bot_token" name="zs_options[bot_token]" class="regular-text" required aria-required="true" aria-describedby="zs_bot_token_d" autocomplete="off" value="<?php echo esc_attr( $o['bot_token'] ); ?>">
						<p class="description" id="zs_bot_token_d">Zorunlu. @BotFather'dan alınan bot token.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="zs_chat_id">Chat ID <span aria-hidden="true">*</span></label></th>
					<td>
						<input type="text" id="zs_chat_id" name="zs_options[chat_id]" class="regular-text" required aria-required="true" aria-describedby="zs_chat_id_d" inputmode="numeric" value="<?php echo esc_attr( $o['chat_id'] ); ?>">
						<p class="description" id="zs_chat_id_d">Zorunlu. Mesajların gönderileceği kullanıcı veya grubun sayısal Chat ID değeri.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Kaydet' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_notices', function () {
	if ( current_user_can( 'manage_options' ) && ! zs_is_configured() ) {
		echo '<div class="notice notice-warning" role="alert"><p>Ziyaretçi Sohbet çalışması için Bot Token ve Chat ID girilmelidir. <a href="' . esc_url( admin_url( 'options-general.php?page=ziyaretci-sohbet' ) ) . '">Ayarlar</a></p></div>';
	}
} );

/* ---------- REST ---------- */

function zs_valid_session( $s ) {
	return is_string( $s ) && preg_match( '/^[a-f0-9]{32}$/', $s );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'zs/v1', '/send', array(
		'methods'             => 'POST',
		'callback'            => 'zs_rest_send',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'zs/v1', '/messages', array(
		'methods'             => 'GET',
		'callback'            => 'zs_rest_messages',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'zs/v1', '/telegram', array(
		'methods'             => 'POST',
		'callback'            => 'zs_rest_telegram',
		'permission_callback' => '__return_true',
	) );
} );

function zs_rest_send( WP_REST_Request $req ) {
	if ( ! zs_is_configured() ) {
		return new WP_Error( 'zs_conf', 'Sohbet şu anda kullanılamıyor.', array( 'status' => 503 ) );
	}
	global $wpdb;
	$session = $req->get_param( 'session' );
	if ( ! zs_valid_session( $session ) ) {
		$session = bin2hex( random_bytes( 16 ) );
	}
	$text = trim( sanitize_textarea_field( (string) $req->get_param( 'message' ) ) );
	if ( '' === $text ) {
		return new WP_Error( 'zs_empty', 'Mesaj boş olamaz.', array( 'status' => 400 ) );
	}
	$text = mb_substr( $text, 0, 1000 );

	$key = 'zs_rl_' . $session;
	if ( get_transient( $key ) ) {
		return new WP_Error( 'zs_rate', 'Lütfen birkaç saniye bekleyin.', array( 'status' => 429 ) );
	}
	set_transient( $key, 1, 2 );

	$o      = zs_get_options();
	$label  = strtoupper( substr( $session, 0, 6 ) );
	$result = zs_telegram( 'sendMessage', array(
		'chat_id' => $o['chat_id'],
		'text'    => "Ziyaretçi #{$label}:\n" . $text . "\n\n(Yanıtlamak için bu mesaja reply yapın)",
	) );
	if ( is_wp_error( $result ) ) {
		return new WP_Error( 'zs_send', 'Mesaj gönderilemedi.', array( 'status' => 502 ) );
	}
	$wpdb->insert( zs_table(), array(
		'session'         => $session,
		'direction'       => 'in',
		'message'         => $text,
		'tg_message_id'   => (int) $result['message_id'],
		'created'         => current_time( 'mysql', true ),
	) );
	return array( 'session' => $session, 'id' => (int) $wpdb->insert_id );
}

function zs_rest_messages( WP_REST_Request $req ) {
	global $wpdb;
	$session = $req->get_param( 'session' );
	if ( ! zs_valid_session( $session ) ) {
		return array( 'messages' => array() );
	}
	$after = (int) $req->get_param( 'after' );
	$rows  = $wpdb->get_results( $wpdb->prepare(
		'SELECT id, direction, message FROM ' . zs_table() . ' WHERE session = %s AND id > %d ORDER BY id ASC LIMIT 100',
		$session,
		$after
	), ARRAY_A );
	return array( 'messages' => $rows );
}

function zs_rest_telegram( WP_REST_Request $req ) {
	global $wpdb;
	$o      = zs_get_options();
	$header = (string) $req->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
	if ( '' === $o['secret'] || ! hash_equals( $o['secret'], $header ) ) {
		return new WP_Error( 'zs_auth', 'Yetkisiz', array( 'status' => 403 ) );
	}
	$update = $req->get_json_params();
	$msg    = isset( $update['message'] ) ? $update['message'] : null;
	if ( ! $msg || ! isset( $msg['chat']['id'], $msg['text'], $msg['reply_to_message']['message_id'] ) ) {
		return array( 'ok' => true );
	}
	if ( (string) $msg['chat']['id'] !== (string) $o['chat_id'] ) {
		return array( 'ok' => true );
	}
	$session = $wpdb->get_var( $wpdb->prepare(
		'SELECT session FROM ' . zs_table() . ' WHERE tg_message_id = %d LIMIT 1',
		(int) $msg['reply_to_message']['message_id']
	) );
	if ( $session ) {
		$wpdb->insert( zs_table(), array(
			'session'       => $session,
			'direction'     => 'out',
			'message'       => mb_substr( sanitize_textarea_field( $msg['text'] ), 0, 2000 ),
			'tg_message_id' => 0,
			'created'       => current_time( 'mysql', true ),
		) );
	} else {
		zs_telegram( 'sendMessage', array(
			'chat_id' => $o['chat_id'],
			'text'    => 'Bu mesaj bir ziyaretçi mesajına yanıt değil; iletilemedi.',
		) );
	}
	return array( 'ok' => true );
}

/* ---------- Ön yüz ---------- */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! zs_is_configured() ) {
		return;
	}
	wp_enqueue_style( 'zs-chat', ZS_URL . 'assets/chat.css', array(), ZS_VERSION );
	wp_enqueue_script( 'zs-chat', ZS_URL . 'assets/chat.js', array(), ZS_VERSION, true );
	wp_localize_script( 'zs-chat', 'zsChat', array(
		'rest' => esc_url_raw( rest_url( 'zs/v1/' ) ),
	) );
} );

add_action( 'wp_footer', function () {
	if ( ! zs_is_configured() ) {
		return;
	}
	?>
	<div id="zs-chat" class="zs-chat">
		<button type="button" id="zs-toggle" class="zs-toggle" aria-expanded="false" aria-controls="zs-panel">Canlı sohbet</button>
		<section id="zs-panel" class="zs-panel" role="dialog" aria-labelledby="zs-title" hidden>
			<h2 id="zs-title" class="zs-title">Canlı sohbet</h2>
			<div id="zs-log" class="zs-log" role="log" aria-live="polite" aria-relevant="additions" aria-label="Sohbet mesajları" tabindex="0"></div>
			<p id="zs-status" class="zs-sr" role="status"></p>
			<form id="zs-form" class="zs-form">
				<label for="zs-input">Mesajınız</label>
				<textarea id="zs-input" rows="2" maxlength="1000" required aria-required="true"></textarea>
				<button type="submit">Gönder</button>
			</form>
			<button type="button" id="zs-close" class="zs-close">Sohbeti kapat</button>
		</section>
	</div>
	<?php
} );
