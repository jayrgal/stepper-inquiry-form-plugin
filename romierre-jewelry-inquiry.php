<?php
/**
 * Plugin Name: Romierre Jewelry - Custom Inquiry Form
 * Plugin URI:  https://romierrejewelry.com
 * Description: A multi-step (stepper) custom jewelry inquiry form. Captures jewelry type, materials, sizing, budget/timeline, and reference/3D sample uploads. Submissions are saved in wp-admin and emailed to your team.
 * Version:     1.0.0
 * Author:      Magnus Cura
 * Text Domain: rji
 */

if ( ! defined( 'ABSPATH' ) ) exit; // No direct access

define( 'RJI_VERSION', '1.0.0' );
define( 'RJI_PATH', plugin_dir_path( __FILE__ ) );
define( 'RJI_URL', plugin_dir_url( __FILE__ ) );

/* ============================================================
 * 1. CUSTOM POST TYPE — stores each inquiry
 * ============================================================ */
add_action( 'init', function () {
	register_post_type( 'rji_inquiry', array(
		'labels' => array(
			'name'          => 'Jewelry Inquiries',
			'singular_name' => 'Jewelry Inquiry',
			'all_items'     => 'All Inquiries',
			'add_new_item'  => 'Add Inquiry',
			'edit_item'     => 'View Inquiry',
			'search_items'  => 'Search Inquiries',
			'not_found'     => 'No inquiries found.',
		),
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_icon'          => 'dashicons-heart',
		'menu_position'      => 25,
		'capability_type'    => 'post',
		'supports'           => array( 'title' ),
		'has_archive'        => false,
	) );
} );

/* Status taxonomy-like meta: New / In Progress / Quoted / Completed */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'rji_details', 'Inquiry Details', 'rji_render_details_box', 'rji_inquiry', 'normal', 'high' );
	add_meta_box( 'rji_status', 'Status', 'rji_render_status_box', 'rji_inquiry', 'side', 'high' );
} );

function rji_render_status_box( $post ) {
	wp_nonce_field( 'rji_save_status', 'rji_status_nonce' );
	$status  = get_post_meta( $post->ID, 'status', true ) ?: 'new';
	$options = array(
		'new'         => 'New',
		'in_progress' => 'In Progress',
		'quoted'      => 'Quoted',
		'completed'   => 'Completed',
	);
	echo '<select name="rji_status" style="width:100%">';
	foreach ( $options as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status, $val, false ), esc_html( $label ) );
	}
	echo '</select>';
}

add_action( 'save_post_rji_inquiry', function ( $post_id ) {
	if ( ! isset( $_POST['rji_status_nonce'] ) || ! wp_verify_nonce( $_POST['rji_status_nonce'], 'rji_save_status' ) ) return;
	if ( isset( $_POST['rji_status'] ) ) {
		update_post_meta( $post_id, 'status', sanitize_text_field( $_POST['rji_status'] ) );
	}
} );

function rji_render_details_box( $post ) {
	$m = function ( $key ) use ( $post ) {
		return esc_html( get_post_meta( $post->ID, $key, true ) );
	};
	$files = get_post_meta( $post->ID, 'reference_files', true );
	?>
	<style>
		.rji-admin-table { width:100%; border-collapse: collapse; }
		.rji-admin-table th { text-align:left; width:220px; padding:10px 12px; background:#f6f7f7; vertical-align:top; }
		.rji-admin-table td { padding:10px 12px; border-bottom:1px solid #eee; vertical-align:top; }
		.rji-section-title { background:#2c2c2c; color:#d4af37; padding:8px 12px; font-weight:600; margin-top:20px; }
		.rji-thumbs img, .rji-thumbs a { display:inline-block; margin:4px; }
		.rji-thumbs img { width:100px; height:100px; object-fit:cover; border:1px solid #ddd; border-radius:4px; }
	</style>

	<div class="rji-section-title">Contact</div>
	<table class="rji-admin-table">
		<tr><th>Name</th><td><?php echo $m('client_name'); ?></td></tr>
		<tr><th>Email</th><td><?php echo $m('client_email'); ?></td></tr>
		<tr><th>Phone</th><td><?php echo $m('client_phone'); ?></td></tr>
	</table>

	<div class="rji-section-title">💍 Jewelry Type &amp; Design</div>
	<table class="rji-admin-table">
		<tr><th>Item</th><td><?php echo $m('item_type'); ?></td></tr>
		<tr><th>Design Concept</th><td><?php echo $m('design_concept'); ?></td></tr>
	</table>

	<div class="rji-section-title">💎 Materials</div>
	<table class="rji-admin-table">
		<tr><th>Metal</th><td><?php echo $m('metal'); ?> <?php echo $m('karat'); ?></td></tr>
		<tr><th>Gemstone(s)</th><td><?php echo $m('gemstones'); ?></td></tr>
		<tr><th>Stone Origin</th><td><?php echo $m('stone_origin'); ?></td></tr>
	</table>

	<div class="rji-section-title">📏 Sizing &amp; Details</div>
	<table class="rji-admin-table">
		<tr><th>Ring Size</th><td><?php echo $m('ring_size') ?: 'N/A'; ?></td></tr>
		<tr><th>Engraving</th><td><?php echo $m('engraving_text') ?: 'None requested'; ?></td></tr>
	</table>

	<div class="rji-section-title">⏱️ Budget &amp; Timeline</div>
	<table class="rji-admin-table">
		<tr><th>Budget Range</th><td><?php echo $m('budget_range'); ?></td></tr>
		<tr><th>Date Needed</th><td><?php echo $m('date_needed') ?: 'No strict deadline'; ?></td></tr>
		<tr><th>Occasion</th><td><?php echo $m('occasion') ?: '—'; ?></td></tr>
	</table>

	<div class="rji-section-title">📎 References / 3D / Sample</div>
	<div class="rji-thumbs">
		<?php
		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$ext = strtolower( pathinfo( $file['url'], PATHINFO_EXTENSION ) );
				if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
					printf( '<a href="%1$s" target="_blank"><img src="%1$s" alt="reference"></a>', esc_url( $file['url'] ) );
				} else {
					printf( '<a href="%1$s" target="_blank">📄 %2$s</a><br>', esc_url( $file['url'] ), esc_html( $file['name'] ) );
				}
			}
		} else {
			echo 'No files uploaded.';
		}
		?>
	</div>
	<?php
}

/* Admin list table columns */
add_filter( 'manage_rji_inquiry_posts_columns', function ( $cols ) {
	$new = array();
	$new['cb']       = $cols['cb'];
	$new['title']    = 'Client / Date';
	$new['item']     = 'Item';
	$new['metal']    = 'Metal';
	$new['budget']   = 'Budget';
	$new['deadline'] = 'Needed By';
	$new['status']   = 'Status';
	return $new;
} );

add_action( 'manage_rji_inquiry_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'item':
			echo esc_html( get_post_meta( $post_id, 'item_type', true ) );
			break;
		case 'metal':
			echo esc_html( get_post_meta( $post_id, 'metal', true ) );
			break;
		case 'budget':
			echo esc_html( get_post_meta( $post_id, 'budget_range', true ) );
			break;
		case 'deadline':
			echo esc_html( get_post_meta( $post_id, 'date_needed', true ) ?: '—' );
			break;
		case 'status':
			$status = get_post_meta( $post_id, 'status', true ) ?: 'new';
			$colors = array( 'new' => '#2271b1', 'in_progress' => '#dba617', 'quoted' => '#8c8f94', 'completed' => '#00a32a' );
			$labels = array( 'new' => 'New', 'in_progress' => 'In Progress', 'quoted' => 'Quoted', 'completed' => 'Completed' );
			printf( '<span style="color:#fff;background:%s;padding:3px 8px;border-radius:3px;font-size:11px;">%s</span>', $colors[ $status ], $labels[ $status ] );
			break;
	}
}, 10, 2 );

/* ============================================================
 * 2. SETTINGS — notification email
 * ============================================================ */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=rji_inquiry', 'Inquiry Form Settings', 'Settings', 'manage_options', 'rji-settings', 'rji_settings_page' );
} );

function rji_settings_page() {
	if ( isset( $_POST['rji_save_settings'] ) && check_admin_referer( 'rji_settings' ) ) {
		update_option( 'rji_notify_email', sanitize_email( $_POST['rji_notify_email'] ) );
		echo '<div class="updated"><p>Saved.</p></div>';
	}
	$email = get_option( 'rji_notify_email', get_option( 'admin_email' ) );
	?>
	<div class="wrap">
		<h1>Inquiry Form Settings</h1>
		<form method="post">
			<?php wp_nonce_field( 'rji_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="rji_notify_email">Notification Email</label></th>
					<td><input type="email" name="rji_notify_email" id="rji_notify_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required>
					<p class="description">New inquiries are sent here. You can add multiple by editing the plugin filter <code>rji_notify_emails</code>.</p></td>
				</tr>
			</table>
			<p><strong>Shortcode:</strong> <code>[romierre_inquiry_form]</code> — place this on any page.</p>
			<?php submit_button( 'Save Settings', 'primary', 'rji_save_settings' ); ?>
		</form>
	</div>
	<?php
}

/* ============================================================
 * 3. ALLOW 3D FILE UPLOAD TYPES (STL / OBJ / GLB)
 * ============================================================ */
add_filter( 'upload_mimes', function ( $mimes ) {
	$mimes['stl'] = 'model/stl';
	$mimes['obj'] = 'text/plain';
	$mimes['glb'] = 'model/gltf-binary';
	return $mimes;
} );
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename, $mimes ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	if ( in_array( $ext, array( 'stl', 'obj', 'glb' ), true ) ) {
		$data['ext']  = $ext;
		$data['type'] = $mimes[ $ext ];
	}
	return $data;
}, 10, 4 );

/* ============================================================
 * 4. SHORTCODE — the stepper form
 * ============================================================ */
add_shortcode( 'romierre_inquiry_form', 'rji_render_form' );

function rji_render_form() {
	ob_start();
	?>
	<div id="rji-form-wrap" class="rji-wrap">

		<div class="rji-progress">
			<?php
			$steps = array( 'Contact & Design', 'Materials', 'Sizing & Details', 'Budget & Timeline', 'References & Sample' );
			foreach ( $steps as $i => $label ) :
				$n = $i + 1;
				?>
				<div class="rji-step-indicator" data-step="<?php echo $n; ?>">
					<span class="rji-step-circle"><?php echo $n; ?></span>
					<span class="rji-step-label"><?php echo esc_html( $label ); ?></span>
				</div>
			<?php endforeach; ?>
			<div class="rji-progress-bar"><div class="rji-progress-bar-fill"></div></div>
		</div>

		<form id="rji-form" novalidate>
			<?php wp_nonce_field( 'rji_submit_inquiry', 'rji_nonce' ); ?>

			<!-- STEP 1: Contact + Jewelry Type & Design -->
			<div class="rji-step active" data-step="1">
				<h3>Let's start with the basics</h3>
				<div class="rji-row">
					<div class="rji-field">
						<label>Full Name *</label>
						<input type="text" name="client_name" required>
					</div>
					<div class="rji-field">
						<label>Email Address *</label>
						<input type="email" name="client_email" required>
					</div>
				</div>
				<div class="rji-field">
					<label>Phone / Viber / WhatsApp Number *</label>
					<input type="tel" name="client_phone" required>
				</div>

				<h4>💍 Jewelry Type &amp; Design</h4>
				<div class="rji-field">
					<label>What are you inquiring about? *</label>
					<div class="rji-pill-group" data-name="item_type">
						<?php foreach ( array( 'Ring', 'Necklace', 'Bracelet', 'Earrings', 'Other' ) as $opt ) : ?>
							<button type="button" class="rji-pill"><?php echo $opt; ?></button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="item_type" required>
				</div>
				<div class="rji-field">
					<label>Design Concept *</label>
					<select name="design_concept" required>
						<option value="">Select one…</option>
						<option>Customize an existing piece</option>
						<option>Design something entirely new</option>
						<option>Recreate a family heirloom</option>
					</select>
				</div>
			</div>

			<!-- STEP 2: Materials -->
			<div class="rji-step" data-step="2">
				<h3>💎 Materials</h3>
				<div class="rji-field">
					<label>Metal *</label>
					<div class="rji-pill-group" data-name="metal">
						<?php foreach ( array( 'Yellow Gold', 'White Gold', 'Rose Gold', 'Platinum' ) as $opt ) : ?>
							<button type="button" class="rji-pill"><?php echo $opt; ?></button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="metal" required>
				</div>
				<div class="rji-field" id="rji-karat-field">
					<label>Karat</label>
					<select name="karat">
						<option value="">Select karat…</option>
						<option>10K</option>
						<option>14K</option>
						<option>18K</option>
						<option>Not applicable (Platinum)</option>
					</select>
				</div>
				<div class="rji-field">
					<label>Primary Gemstone(s) — select all that apply</label>
					<div class="rji-checkbox-group" data-name="gemstones">
						<?php foreach ( array( 'Natural Diamond', 'Lab-Grown Diamond', 'Sapphire', 'Emerald', 'Ruby', 'Pearl', 'Other' ) as $opt ) : ?>
							<label class="rji-checkbox"><input type="checkbox" value="<?php echo esc_attr( $opt ); ?>"> <?php echo $opt; ?></label>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="gemstones">
				</div>
				<div class="rji-field">
					<label>Stone Origin *</label>
					<select name="stone_origin" required>
						<option value="">Select one…</option>
						<option>I need the jeweler to source the stone(s)</option>
						<option>I have my own loose stone / existing jewelry to reset</option>
					</select>
				</div>
			</div>

			<!-- STEP 3: Sizing & Details -->
			<div class="rji-step" data-step="3">
				<h3>📏 Sizing &amp; Details</h3>
				<div class="rji-field" id="rji-ring-size-field" style="display:none;">
					<label>Ring Size</label>
					<input type="text" name="ring_size" placeholder="e.g. US 6 / PH 12 — not sure? Leave blank, we'll help you measure.">
				</div>
				<div class="rji-field">
					<label class="rji-checkbox"><input type="checkbox" id="rji-wants-engraving"> I'd like an engraving</label>
				</div>
				<div class="rji-field" id="rji-engraving-field" style="display:none;">
					<label>Engraving Text</label>
					<input type="text" name="engraving_text" maxlength="60" placeholder="Name, date, or short message (max 60 characters)">
				</div>
			</div>

			<!-- STEP 4: Budget & Timeline -->
			<div class="rji-step" data-step="4">
				<h3>⏱️ Budget &amp; Timeline</h3>
				<div class="rji-field">
					<label>Budget Range (PHP) *</label>
					<select name="budget_range" required>
						<option value="">Select a range…</option>
						<option>Under ₱20,000</option>
						<option>₱20,000 – ₱50,000</option>
						<option>₱50,000 – ₱100,000</option>
						<option>₱100,000 – ₱200,000</option>
						<option>₱200,000+</option>
					</select>
				</div>
				<div class="rji-row">
					<div class="rji-field">
						<label>Date Needed</label>
						<input type="date" name="date_needed">
					</div>
					<div class="rji-field">
						<label>Occasion (optional)</label>
						<input type="text" name="occasion" placeholder="Wedding, anniversary, birthday…">
					</div>
				</div>
				<p class="rji-note">Custom work typically takes 6–10 weeks. We'll confirm feasibility once we review your inquiry.</p>
			</div>

			<!-- STEP 5: References / 3D / Sample -->
			<div class="rji-step" data-step="5">
				<h3>📎 References, Sketches, or 3D/Sample File</h3>
				<p class="rji-note">Upload 2–3 reference photos, sketches, or a moodboard — or a 3D/sample file (STL, OBJ, GLB) if you already have one. Images, PDF, STL, OBJ, GLB accepted, max 10MB each, up to 5 files.</p>
				<div class="rji-field">
					<div class="rji-dropzone" id="rji-dropzone">
						<span>📤 Click or drag files here to upload</span>
						<input type="file" name="jewelry_files[]" id="rji-file-input" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.stl,.obj,.glb">
					</div>
					<div class="rji-file-list" id="rji-file-list"></div>
				</div>
				<div class="rji-field">
					<label>Anything else we should know? (optional)</label>
					<textarea name="notes" rows="3" placeholder="Any additional details about your dream piece…"></textarea>
				</div>
			</div>

			<div class="rji-nav">
				<button type="button" class="rji-btn rji-btn-secondary" id="rji-prev" style="visibility:hidden;">Back</button>
				<button type="button" class="rji-btn rji-btn-primary" id="rji-next">Next</button>
				<button type="submit" class="rji-btn rji-btn-primary" id="rji-submit" style="display:none;">Submit Inquiry</button>
			</div>

			<div class="rji-message" id="rji-message"></div>
		</form>

		<div class="rji-success" id="rji-success" style="display:none;">
			<div class="rji-success-icon">✓</div>
			<h3>Thank you, <span id="rji-success-name"></span>!</h3>
			<p>Your custom jewelry inquiry has been received. Our team will review your details and reach out within 1–2 business days.</p>
		</div>
	</div>

	<?php echo rji_styles(); ?>
	<?php echo rji_script(); ?>
	<?php
	return ob_get_clean();
}

/* ============================================================
 * 5. AJAX HANDLER
 * ============================================================ */
add_action( 'wp_ajax_rji_submit_inquiry', 'rji_handle_submission' );
add_action( 'wp_ajax_nopriv_rji_submit_inquiry', 'rji_handle_submission' );

function rji_handle_submission() {
	check_ajax_referer( 'rji_submit_inquiry', 'nonce' );

	$name  = isset( $_POST['client_name'] ) ? sanitize_text_field( $_POST['client_name'] ) : '';
	$email = isset( $_POST['client_email'] ) ? sanitize_email( $_POST['client_email'] ) : '';

	if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Please provide a valid name and email.' ) );
	}

	$fields = array(
		'client_name'    => sanitize_text_field( $_POST['client_name'] ?? '' ),
		'client_email'   => sanitize_email( $_POST['client_email'] ?? '' ),
		'client_phone'   => sanitize_text_field( $_POST['client_phone'] ?? '' ),
		'item_type'      => sanitize_text_field( $_POST['item_type'] ?? '' ),
		'design_concept' => sanitize_text_field( $_POST['design_concept'] ?? '' ),
		'metal'          => sanitize_text_field( $_POST['metal'] ?? '' ),
		'karat'          => sanitize_text_field( $_POST['karat'] ?? '' ),
		'gemstones'      => sanitize_text_field( $_POST['gemstones'] ?? '' ),
		'stone_origin'   => sanitize_text_field( $_POST['stone_origin'] ?? '' ),
		'ring_size'      => sanitize_text_field( $_POST['ring_size'] ?? '' ),
		'engraving_text' => sanitize_text_field( $_POST['engraving_text'] ?? '' ),
		'budget_range'   => sanitize_text_field( $_POST['budget_range'] ?? '' ),
		'date_needed'    => sanitize_text_field( $_POST['date_needed'] ?? '' ),
		'occasion'       => sanitize_text_field( $_POST['occasion'] ?? '' ),
		'notes'          => sanitize_textarea_field( $_POST['notes'] ?? '' ),
	);

	// Create the post
	$post_id = wp_insert_post( array(
		'post_type'   => 'rji_inquiry',
		'post_title'  => sprintf( '%s — %s (%s)', $fields['client_name'], $fields['item_type'] ?: 'Inquiry', date( 'Y-m-d' ) ),
		'post_status' => 'publish',
	) );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		wp_send_json_error( array( 'message' => 'Something went wrong saving your inquiry. Please try again.' ) );
	}

	foreach ( $fields as $key => $val ) {
		update_post_meta( $post_id, $key, $val );
	}
	update_post_meta( $post_id, 'status', 'new' );

	// Handle file uploads
	$uploaded_files = array();
	if ( ! empty( $_FILES['jewelry_files'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files      = $_FILES['jewelry_files'];
		$file_count = count( $files['name'] );
		$max_files  = 5;
		$max_size   = 10 * 1024 * 1024; // 10MB

		for ( $i = 0; $i < min( $file_count, $max_files ); $i++ ) {
			if ( empty( $files['name'][ $i ] ) || $files['error'][ $i ] !== 0 ) continue;
			if ( $files['size'][ $i ] > $max_size ) continue;

			$single_file = array(
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			);

			$_FILES = array( 'rji_single' => $single_file );
			$attachment_id = media_handle_upload( 'rji_single', $post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				$uploaded_files[] = array(
					'name' => $single_file['name'],
					'url'  => wp_get_attachment_url( $attachment_id ),
					'id'   => $attachment_id,
				);
			}
		}
	}
	update_post_meta( $post_id, 'reference_files', $uploaded_files );

	// Email notification to team
	$notify_email = get_option( 'rji_notify_email', get_option( 'admin_email' ) );
	$notify_emails = apply_filters( 'rji_notify_emails', array( $notify_email ) );

	$body  = "New custom jewelry inquiry received:\n\n";
	$body .= "Name: {$fields['client_name']}\n";
	$body .= "Email: {$fields['client_email']}\n";
	$body .= "Phone: {$fields['client_phone']}\n\n";
	$body .= "Item: {$fields['item_type']}\n";
	$body .= "Design Concept: {$fields['design_concept']}\n";
	$body .= "Metal: {$fields['metal']} {$fields['karat']}\n";
	$body .= "Gemstones: {$fields['gemstones']}\n";
	$body .= "Stone Origin: {$fields['stone_origin']}\n";
	$body .= "Ring Size: {$fields['ring_size']}\n";
	$body .= "Engraving: {$fields['engraving_text']}\n\n";
	$body .= "Budget: {$fields['budget_range']}\n";
	$body .= "Date Needed: {$fields['date_needed']}\n";
	$body .= "Occasion: {$fields['occasion']}\n\n";
	$body .= "Notes: {$fields['notes']}\n\n";
	$body .= "Files uploaded: " . count( $uploaded_files ) . "\n";
	foreach ( $uploaded_files as $f ) {
		$body .= "- {$f['name']}: {$f['url']}\n";
	}
	$body .= "\nView full inquiry: " . admin_url( "post.php?post={$post_id}&action=edit" );

	wp_mail( $notify_emails, 'New Jewelry Inquiry: ' . $fields['client_name'], $body );

	// Confirmation email to client
	$client_body  = "Hi {$fields['client_name']},\n\n";
	$client_body .= "Thank you for your custom jewelry inquiry with Romierre Jewelry! We've received the following details:\n\n";
	$client_body .= "Item: {$fields['item_type']}\n";
	$client_body .= "Metal: {$fields['metal']} {$fields['karat']}\n";
	$client_body .= "Budget Range: {$fields['budget_range']}\n\n";
	$client_body .= "Our team will review your inquiry and get back to you within 1-2 business days. Custom pieces typically take 6-10 weeks to complete.\n\n";
	$client_body .= "Warm regards,\nRomierre Jewelry";

	wp_mail( $fields['client_email'], 'We received your custom jewelry inquiry — Romierre Jewelry', $client_body );

	wp_send_json_success( array(
		'message' => 'Inquiry submitted successfully.',
		'name'    => $fields['client_name'],
	) );
}

/* ============================================================
 * 6. STYLES
 * ============================================================ */
function rji_styles() {
	ob_start();
	?>
	<style>
	.rji-wrap { max-width: 720px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #2b2b2b; }
	.rji-wrap * { box-sizing: border-box; }
	.rji-progress { margin-bottom: 32px; position: relative; }
	.rji-progress-bar { position: absolute; top: 18px; left: 5%; right: 5%; height: 2px; background: #e6e0d4; z-index: 0; }
	.rji-progress-bar-fill { height: 2px; background: #b8952f; width: 0%; transition: width .35s ease; }
	.rji-progress { display: flex; justify-content: space-between; }
	.rji-step-indicator { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; z-index: 1; }
	.rji-step-circle { width: 36px; height: 36px; border-radius: 50%; background: #f4f1ea; border: 2px solid #e6e0d4; color: #999; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; transition: all .3s ease; }
	.rji-step-label { font-size: 11px; color: #999; margin-top: 6px; text-align: center; max-width: 80px; }
	.rji-step-indicator.done .rji-step-circle { background: #b8952f; border-color: #b8952f; color: #fff; }
	.rji-step-indicator.current .rji-step-circle { background: #fff; border-color: #b8952f; color: #b8952f; box-shadow: 0 0 0 4px rgba(184,149,47,.15); }
	.rji-step-indicator.current .rji-step-label, .rji-step-indicator.done .rji-step-label { color: #2b2b2b; }

	.rji-step { display: none; animation: rjiFade .3s ease; }
	.rji-step.active { display: block; }
	@keyframes rjiFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

	.rji-wrap h3 { font-size: 19px; margin: 0 0 18px; font-weight: 600; }
	.rji-wrap h4 { font-size: 15px; margin: 24px 0 10px; color: #b8952f; }
	.rji-row { display: flex; gap: 16px; }
	.rji-row .rji-field { flex: 1; }
	.rji-field { margin-bottom: 18px; }
	.rji-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #444; }
	.rji-field input[type=text], .rji-field input[type=email], .rji-field input[type=tel], .rji-field input[type=date], .rji-field select, .rji-field textarea {
		width: 100%; padding: 11px 13px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: #fff; transition: border-color .2s;
	}
	.rji-field input:focus, .rji-field select:focus, .rji-field textarea:focus { outline: none; border-color: #b8952f; }
	.rji-note { font-size: 13px; color: #888; background: #f9f7f2; padding: 10px 14px; border-radius: 6px; border-left: 3px solid #b8952f; }

	.rji-pill-group { display: flex; flex-wrap: wrap; gap: 8px; }
	.rji-pill { padding: 9px 16px; border: 1px solid #ddd; border-radius: 30px; background: #fff; cursor: pointer; font-size: 13px; transition: all .2s; }
	.rji-pill:hover { border-color: #b8952f; }
	.rji-pill.selected { background: #b8952f; border-color: #b8952f; color: #fff; }

	.rji-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px 18px; }
	.rji-checkbox { font-size: 13px; font-weight: 400; display: flex; align-items: center; gap: 6px; cursor: pointer; }

	.rji-dropzone { border: 2px dashed #d8cba8; border-radius: 8px; padding: 32px; text-align: center; cursor: pointer; position: relative; background: #fbfaf6; font-size: 14px; color: #888; transition: border-color .2s; }
	.rji-dropzone:hover, .rji-dropzone.dragover { border-color: #b8952f; color: #b8952f; }
	.rji-dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
	.rji-file-list { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 10px; }
	.rji-file-chip { display: flex; align-items: center; gap: 8px; background: #f4f1ea; padding: 6px 10px; border-radius: 6px; font-size: 12px; }
	.rji-file-chip img { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; }
	.rji-file-chip .rji-remove { cursor: pointer; color: #c0392b; font-weight: bold; }

	.rji-nav { display: flex; justify-content: space-between; margin-top: 28px; }
	.rji-btn { padding: 12px 28px; border-radius: 30px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all .2s; }
	.rji-btn-primary { background: #2b2b2b; color: #fff; }
	.rji-btn-primary:hover { background: #b8952f; }
	.rji-btn-secondary { background: transparent; color: #666; border: 1px solid #ddd; }
	.rji-btn-secondary:hover { border-color: #999; }
	.rji-btn:disabled { opacity: .6; cursor: not-allowed; }

	.rji-message { margin-top: 14px; font-size: 13px; color: #c0392b; }
	.rji-success { text-align: center; padding: 50px 20px; }
	.rji-success-icon { width: 60px; height: 60px; border-radius: 50%; background: #b8952f; color: #fff; font-size: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
	.rji-success h3 { font-size: 20px; margin-bottom: 8px; }
	.rji-success p { color: #666; max-width: 420px; margin: 0 auto; }

	@media (max-width: 600px) {
		.rji-row { flex-direction: column; gap: 0; }
		.rji-step-label { display: none; }
	}
	</style>
	<?php
	return ob_get_clean();
}

/* ============================================================
 * 7. SCRIPT
 * ============================================================ */
function rji_script() {
	$ajax_url = admin_url( 'admin-ajax.php' );
	ob_start();
	?>
	<script>
	(function(){
		var currentStep = 1;
		var totalSteps = 5;
		var wrap = document.getElementById('rji-form-wrap');
		var form = document.getElementById('rji-form');
		var selectedFiles = [];

		function updateProgress() {
			document.querySelectorAll('.rji-step-indicator').forEach(function(el){
				var s = parseInt(el.dataset.step, 10);
				el.classList.remove('done','current');
				if (s < currentStep) el.classList.add('done');
				if (s === currentStep) el.classList.add('current');
			});
			var pct = ((currentStep - 1) / (totalSteps - 1)) * 100;
			wrap.querySelector('.rji-progress-bar-fill').style.width = pct + '%';

			wrap.querySelector('#rji-prev').style.visibility = currentStep === 1 ? 'hidden' : 'visible';
			wrap.querySelector('#rji-next').style.display = currentStep === totalSteps ? 'none' : 'inline-block';
			wrap.querySelector('#rji-submit').style.display = currentStep === totalSteps ? 'inline-block' : 'none';
		}

		function showStep(n) {
			wrap.querySelectorAll('.rji-step').forEach(function(el){
				el.classList.toggle('active', parseInt(el.dataset.step,10) === n);
			});
			updateProgress();
		}

		function validateStep(n) {
			var stepEl = wrap.querySelector('.rji-step[data-step="'+n+'"]');
			var valid = true;
			var msg = '';
			stepEl.querySelectorAll('[required]').forEach(function(field){
				if (!field.value || !field.value.trim()) {
					valid = false;
					field.style.borderColor = '#c0392b';
					msg = 'Please fill in all required fields before continuing.';
				} else {
					field.style.borderColor = '#ddd';
				}
			});
			if (n === 1) {
				var email = stepEl.querySelector('[name="client_email"]');
				if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
					valid = false; msg = 'Please enter a valid email address.'; email.style.borderColor = '#c0392b';
				}
			}
			document.getElementById('rji-message').textContent = valid ? '' : msg;
			return valid;
		}

		// Pill groups (single-select)
		wrap.querySelectorAll('.rji-pill-group').forEach(function(group){
			var name = group.dataset.name;
			var hidden = wrap.querySelector('input[type=hidden][name="'+name+'"]');
			group.querySelectorAll('.rji-pill').forEach(function(pill){
				pill.addEventListener('click', function(){
					group.querySelectorAll('.rji-pill').forEach(function(p){ p.classList.remove('selected'); });
					pill.classList.add('selected');
					hidden.value = pill.textContent.trim();

					if (name === 'item_type') {
						var isRing = pill.textContent.trim() === 'Ring';
						document.getElementById('rji-ring-size-field').style.display = isRing ? 'block' : 'none';
					}
					if (name === 'metal') {
						var isPlatinum = pill.textContent.trim() === 'Platinum';
						document.getElementById('rji-karat-field').style.display = isPlatinum ? 'none' : 'block';
					}
				});
			});
		});

		// Checkbox groups (multi-select, joined into hidden field)
		wrap.querySelectorAll('.rji-checkbox-group').forEach(function(group){
			var name = group.dataset.name;
			var hidden = wrap.querySelector('input[type=hidden][name="'+name+'"]');
			group.querySelectorAll('input[type=checkbox]').forEach(function(cb){
				cb.addEventListener('change', function(){
					var checked = Array.from(group.querySelectorAll('input[type=checkbox]:checked')).map(function(c){ return c.value; });
					hidden.value = checked.join(', ');
				});
			});
		});

		// Engraving toggle
		var engravingToggle = document.getElementById('rji-wants-engraving');
		if (engravingToggle) {
			engravingToggle.addEventListener('change', function(){
				document.getElementById('rji-engraving-field').style.display = this.checked ? 'block' : 'none';
				if (!this.checked) document.querySelector('[name="engraving_text"]').value = '';
			});
		}

		// File upload
		var dropzone = document.getElementById('rji-dropzone');
		var fileInput = document.getElementById('rji-file-input');
		var fileList = document.getElementById('rji-file-list');

		function renderFiles() {
			fileList.innerHTML = '';
			selectedFiles.forEach(function(file, idx){
				var chip = document.createElement('div');
				chip.className = 'rji-file-chip';
				var isImg = /\.(jpe?g|png|gif|webp)$/i.test(file.name);
				if (isImg) {
					var img = document.createElement('img');
					img.src = URL.createObjectURL(file);
					chip.appendChild(img);
				}
				var span = document.createElement('span');
				span.textContent = file.name.length > 22 ? file.name.slice(0,20) + '…' : file.name;
				chip.appendChild(span);
				var remove = document.createElement('span');
				remove.className = 'rji-remove';
				remove.textContent = '✕';
				remove.addEventListener('click', function(){
					selectedFiles.splice(idx, 1);
					syncFileInput();
					renderFiles();
				});
				chip.appendChild(remove);
				fileList.appendChild(chip);
			});
		}

		function syncFileInput() {
			var dt = new DataTransfer();
			selectedFiles.forEach(function(f){ dt.items.add(f); });
			fileInput.files = dt.files;
		}

		fileInput.addEventListener('change', function(){
			Array.from(fileInput.files).forEach(function(f){
				if (selectedFiles.length < 5 && f.size <= 10*1024*1024) selectedFiles.push(f);
			});
			syncFileInput();
			renderFiles();
		});
		['dragover','dragleave','drop'].forEach(function(evt){
			dropzone.addEventListener(evt, function(e){
				e.preventDefault();
				dropzone.classList.toggle('dragover', evt === 'dragover');
			});
		});
		dropzone.addEventListener('drop', function(e){
			Array.from(e.dataTransfer.files).forEach(function(f){
				if (selectedFiles.length < 5 && f.size <= 10*1024*1024) selectedFiles.push(f);
			});
			syncFileInput();
			renderFiles();
		});

		// Navigation
		document.getElementById('rji-next').addEventListener('click', function(){
			if (!validateStep(currentStep)) return;
			currentStep = Math.min(currentStep + 1, totalSteps);
			showStep(currentStep);
		});
		document.getElementById('rji-prev').addEventListener('click', function(){
			currentStep = Math.max(currentStep - 1, 1);
			showStep(currentStep);
		});

		// Submit
		form.addEventListener('submit', function(e){
			e.preventDefault();
			if (!validateStep(currentStep)) return;

			var submitBtn = document.getElementById('rji-submit');
			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting…';

			var fd = new FormData(form);
			fd.append('action', 'rji_submit_inquiry');
			fd.append('nonce', form.querySelector('[name="rji_nonce"]').value);

			fetch('<?php echo esc_js( $ajax_url ); ?>', { method: 'POST', body: fd })
				.then(function(r){ return r.json(); })
				.then(function(res){
					if (res.success) {
						document.getElementById('rji-success-name').textContent = res.data.name;
						form.style.display = 'none';
						wrap.querySelector('.rji-progress').style.display = 'none';
						document.getElementById('rji-success').style.display = 'block';
					} else {
						document.getElementById('rji-message').textContent = res.data.message || 'Something went wrong. Please try again.';
						submitBtn.disabled = false;
						submitBtn.textContent = 'Submit Inquiry';
					}
				})
				.catch(function(){
					document.getElementById('rji-message').textContent = 'Network error. Please try again.';
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit Inquiry';
				});
		});

		showStep(1);
	})();
	</script>
	<?php
	return ob_get_clean();
}
