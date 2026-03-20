<?php if (! function_exists('exertio_theme_setup')) :

	add_action('after_setup_theme', 'exertio_theme_setup');
	function exertio_theme_setup()
	{

		load_theme_textdomain('exertio_theme', get_template_directory() . '/languages');

		/* Theme Utilities */
		require trailingslashit(get_template_directory()) . 'inc/utilities.php';
		require trailingslashit(get_template_directory()) . 'inc/theme-settings.php';
		require trailingslashit(get_template_directory()) . "inc/classes/index.php";
		require trailingslashit(get_template_directory()) . "inc/nav.php";
		require trailingslashit(get_template_directory()) . 'tgm/tgm-init.php';
		require trailingslashit(get_template_directory()) . "inc/zoom/zoom-authorization.php";
		require trailingslashit(get_template_directory()) . "inc/shop-func.php";


		add_theme_support('woocommerce');
		add_theme_support('automatic-feed-links');
		add_theme_support('title-tag');
		add_theme_support('post-thumbnails');


		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		if (in_array('redux-framework/redux-framework.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			require_once(dirname(__FILE__) . '/inc/options-init.php');
		}

		add_image_size('blog-grid-img', 420, 250, true);
		add_image_size('service_grid_img', 400, 270, true);
		add_image_size('service_detail_img', 860, 450, true);
	}
endif;

add_action('wp_enqueue_scripts', 'exertio_scripts', 11);



//enque admin script for admin dashoboard button 
function enqueue_custom_dashboard_scripts()
{
	wp_enqueue_script('custom-dashboard', get_template_directory_uri() . '/js/admin.js', array('jquery'), '1.0', true);
	wp_enqueue_style('admin-styles', get_template_directory_uri() . '/css/admin-style.css');
}
add_action('admin_enqueue_scripts', 'enqueue_custom_dashboard_scripts');
//End of the code .
function exertio_scripts()
{
	$is_rtl = false;
	if (is_rtl()) {
		$is_rtl = true;
	}
	global $exertio_theme_options;

	function exertio_fonts_url()
	{
		$fonts_url = '';
		$poppins = _x('on', 'Poppins font: on or off', 'exertio_theme');
		if ('off' !== $poppins) {
			$font_families = array();
			if ('off' !== $poppins) {
				$font_families[] = 'Poppins:400,500,600';
			}
			$query_args = array(
				'family' => urlencode(implode('%7C', $font_families)),
				'subset' => urlencode('latin,latin-ext'),
			);
			$fonts_url = add_query_arg($query_args, 'https://fonts.googleapis.com/css');
		}
		return urldecode($fonts_url);
	}
	wp_enqueue_style('exertio-theme-fonts', exertio_fonts_url(), array(), null);

	if (is_singular()) {
		wp_enqueue_script('nicescroll', trailingslashit(get_template_directory_uri()) . 'js/jquery.nicescroll.min.js', array('jquery'), false, true);
		wp_enqueue_script('jquery.imageview', trailingslashit(get_template_directory_uri()) . 'js/jquery.imageview.js', array('jquery'), false, true);
	}
	if (is_singular('freelancer')  || is_singular('projects') || is_singular('employer') || is_singular('services') || !is_front_page() && is_home()) {
		wp_enqueue_style('fancybox', trailingslashit(get_template_directory_uri()) . 'css/jquery.fancybox.min.css');
		wp_enqueue_script('jquery-fancybox', trailingslashit(get_template_directory_uri()) . 'js/jquery.fancybox.min.js', false, false, true);
	}
	wp_enqueue_script("sb-menu", trailingslashit(get_template_directory_uri()) . "js/sbmenu.js", false, false, true);
	wp_enqueue_script("isotope", trailingslashit(get_template_directory_uri()) . "js/isotope.js", false, false, true);
	wp_enqueue_script("masonry");
	wp_enqueue_style('owl-carousel', trailingslashit(get_template_directory_uri()) . 'css/owl.carousel.min.css');
	wp_enqueue_script('owl-carousel', trailingslashit(get_template_directory_uri()) . 'js/owl.carousel.min.js', false, false, false);
	//countdown library
	wp_enqueue_script('jquery-countdown', trailingslashit(get_template_directory_uri()) . 'js/jquery.countdown.min.js', array('jquery'), true, true);

	if (is_page_template('page-profile.php')) {
		wp_enqueue_script('bootstrap-js', trailingslashit(get_template_directory_uri()) . 'js/bootstrap4.min.js', false, false, true);
	} else {
		wp_enqueue_script('bootstrap-js', trailingslashit(get_template_directory_uri()) . 'js/bootstrap.bundle.min.js', false, false, true);
	}

	if (is_page_template('page-login.php') || is_page_template('page-register.php')) {
		wp_enqueue_script('passtrength-js', trailingslashit(get_template_directory_uri()) . 'js/jquery.passtrength.min.js', false, false, true);
		wp_enqueue_style('passtrength-css', trailingslashit(get_template_directory_uri()) . 'css/passtrength.css');
	}
	wp_enqueue_script('smoke-js', trailingslashit(get_template_directory_uri()) . 'js/smoke.min.js', false, false, true);
	wp_enqueue_script('toastr', trailingslashit(get_template_directory_uri()) . 'js/toastr.min.js', false, false, true);
	wp_enqueue_script('exertio-select2', trailingslashit(get_template_directory_uri()) . 'js/select2.full.min.js', false, false, true);
	//wp_enqueue_script('jquery-cookie', trailingslashit(get_template_directory_uri()) . 'js/jquery.cookie.min.js', false, false, true);
	wp_enqueue_script('jquery-ui-sortable');
	wp_enqueue_script('jquery-richtext', trailingslashit(get_template_directory_uri()) . 'js/jquery.richtext.min.js', false, false, true);
	wp_enqueue_script('jquery.flexslider', trailingslashit(get_template_directory_uri()) . 'js/jquery.flexslider.js', false, false, true);

	wp_enqueue_script('exertio-rating', trailingslashit(get_template_directory_uri()) . 'js/rating.js', false, false, true);
	wp_enqueue_script('protip', trailingslashit(get_template_directory_uri()) . 'js/protip.min.js', array('jquery'), false, true);
	wp_enqueue_script('youtube-popup', trailingslashit(get_template_directory_uri()) . 'js/youtube-popup-jquery.js', array('jquery'), false, true);
	wp_enqueue_script('waypoints', trailingslashit(get_template_directory_uri()) . 'js/jquery.waypoints.min.js', array('jquery'), false, true);
	wp_enqueue_script('counter', trailingslashit(get_template_directory_uri()) . 'js/counter.js', array('jquery'), false, true);



	if (is_page_template('page-profile.php')) {
		wp_enqueue_script('google-map', '//maps.googleapis.com/maps/api/js?key=' . $exertio_theme_options['google_map_key'] . '&libraries=places', false, false, true);
		wp_enqueue_script('jquery-confirm', trailingslashit(get_template_directory_uri()) . 'js/jquery-confirm.js', array('jquery'), false, true);
		wp_enqueue_script('jquery-datetimepicker', trailingslashit(get_template_directory_uri()) . 'js/jquery.datetimepicker.full.js', array('jquery'), true, true);

		//wp_enqueue_script('exertio-theme-profile', trailingslashit(get_template_directory_uri()) . 'js/custom-script-profile.js', array('jquery'), false, true);

	}
	if (is_singular('projects')) {
		wp_enqueue_script('google-map', '//maps.googleapis.com/maps/api/js?key=' . $exertio_theme_options['google_map_key'] . '&libraries=places', false, false, true);
	}


	if (is_singular('services')) {
		wp_enqueue_script('jquery-confirm', trailingslashit(get_template_directory_uri()) . 'js/jquery-confirm.js', array('jquery'), false, true);
		wp_enqueue_style('jquery-confirm', trailingslashit(get_template_directory_uri()) . 'css/dashboard/jquery-confirm.css');
	}

	/* Load the stylesheets. */
	if (is_page_template('page-profile.php')) {
		if (is_rtl()) {
			wp_enqueue_style('bootstrap4-rtl', trailingslashit(get_template_directory_uri()) . 'css/dashboard/bootstrap4-rtl.min.css');
		} else {
			wp_enqueue_style('bootstrap', trailingslashit(get_template_directory_uri()) . 'css/bootstrap4.min.css');
		}
	} else {
		if (is_rtl()) {
			wp_enqueue_style('bootstrap-rtl', trailingslashit(get_template_directory_uri()) . 'css/bootstrap.rtl.min.css');
		} else {
			wp_enqueue_style('bootstrap', trailingslashit(get_template_directory_uri()) . 'css/bootstrap.min.css');
		}
	}

	wp_enqueue_style('smoke-style', trailingslashit(get_template_directory_uri()) . 'css/smoke.min.css');
	wp_enqueue_style('pretty-checkbox', trailingslashit(get_template_directory_uri()) . 'css/pretty-checkbox.min.css');
	wp_enqueue_style('toastr-style', trailingslashit(get_template_directory_uri()) . 'css/toastr.min.css');
	wp_enqueue_style('select2', trailingslashit(get_template_directory_uri()) . 'css/select2.min.css');
	wp_enqueue_style('web-font-icons', trailingslashit(get_template_directory_uri()) . 'css/all.min.css');
	wp_enqueue_style('richtext', trailingslashit(get_template_directory_uri()) . 'css/richtext.min.css');
	if (is_page_template('page-profile.php')) {
		wp_enqueue_style('jquery-datetimepicker', trailingslashit(get_template_directory_uri()) . 'css/dashboard/jquery.datetimepicker.min.css');
	}
	wp_enqueue_style('flexslider', trailingslashit(get_template_directory_uri()) . 'css/flexslider.css');

	wp_enqueue_style('protip', trailingslashit(get_template_directory_uri()) . 'css/protip.min.css');
	wp_enqueue_style('youtube-popup', trailingslashit(get_template_directory_uri()) . 'css/youtube-popup.css');

	wp_enqueue_style('custom-offer', trailingslashit(get_template_directory_uri()) . 'css/custom-offer.css');

	/*FRONTEND STYLE ENQUEUE*/
	wp_enqueue_style('exertio-sbmenu', trailingslashit(get_template_directory_uri()) . 'css/sbmenu.css');
	if (!is_page_template('page-profile.php')) {
		wp_enqueue_style('exertio-style', trailingslashit(get_template_directory_uri()) . 'css/theme.css');
		if (is_rtl()) {
			wp_enqueue_style('exertio-style-rtl', trailingslashit(get_template_directory_uri()) . 'css/theme-rtl.css');
		}
	}
	if (is_page_template('page-profile.php')) {
		wp_enqueue_style('materialdesignicons', trailingslashit(get_template_directory_uri()) . 'css/dashboard/materialdesignicons.min.css');
		wp_enqueue_style('jquery-confirm', trailingslashit(get_template_directory_uri()) . 'css/dashboard/jquery-confirm.css');


		wp_enqueue_style('dashboard-style', trailingslashit(get_template_directory_uri()) . 'css/dashboard/style.css');
		wp_enqueue_style('dashboard-style-rtl', trailingslashit(get_template_directory_uri()) . 'css/dashboard/style-rtl.css');
	}
	if (is_page_template('page-login.php') || is_page_template('page-register.php')) {
		wp_enqueue_style('owl-carousel', trailingslashit(get_template_directory_uri()) . 'css/owl.carousel.min.css');
		wp_enqueue_script('owl-carousel', trailingslashit(get_template_directory_uri()) . 'js/owl.carousel.min.js', false, false, true);
	}
	if (is_page_template('page-services-search.php') || is_page_template('page-project-search.php') || is_page_template('page-freelancer-search.php')  || is_page_template('page-profile.php')) {
		wp_enqueue_style('ion-rangeslider', trailingslashit(get_template_directory_uri()) . 'css/ion-rangeslider.min.css');
		wp_enqueue_script('ion-rangeslider', trailingslashit(get_template_directory_uri()) . 'js/ion.rangeslider.min.js', false, false, true);
	}

	/* Google Recaptcha JS */
	wp_enqueue_script('recaptcha', '//www.google.com/recaptcha/api.js', false, false, true);
	wp_enqueue_script('exertio-theme', trailingslashit(get_template_directory_uri()) . 'js/custom-script.js',  array('jquery'), false, true);
	wp_enqueue_script('exertio-charts', trailingslashit(get_template_directory_uri()) . 'js/chart.min.js', false, false, true);
	wp_enqueue_script('exertio-stats', trailingslashit(get_template_directory_uri()) . 'js/stats.js', false, false, true);

	/* ZOOM MEETINGS */
	if (in_array('redux-framework/redux-framework.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		if (is_page_template('page-profile.php') && $exertio_theme_options['zoom_meeting_btn'] == 1) {
			wp_enqueue_script('exertio-zoom', trailingslashit(get_template_directory_uri()) . 'inc/zoom/zoom-meeting.js', array('jquery'), false, true);
		}
	}


	if (in_array('redux-framework/redux-framework.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		function inline_typography()
		{
			wp_enqueue_style('theme_custom_css', get_template_directory_uri() . '/css/custom_style.css');

			global $exertio_theme_options;
			$h2_color = $exertio_theme_options['opt-typography-body']['color'];
			$main_btn_color = $exertio_theme_options['opt-theme-btn-color']['regular'];
			$main_btn_color_hover = $exertio_theme_options['opt-theme-btn-color']['hover'];
			$main_btn_color_shadow = $exertio_theme_options['opt-theme-btn-shadow-color']['rgba'];
			$main_btn_color_text = $exertio_theme_options['opt-theme-btn-text-color']['regular'];
			$main_btn_hover_color_text = $exertio_theme_options['opt-theme-btn-text-color']['hover'];

			$sec_btn_color = $exertio_theme_options['second-opt-theme-btn-color']['regular'];
			$sec_btn_color_hover = $exertio_theme_options['second-opt-theme-btn-color']['hover'];
			$sec_btn_color_shadow = $exertio_theme_options['second-opt-theme-btn-shadow-color']['rgba'];
			$sec_btn_color_text = $exertio_theme_options['second-opt-theme-btn-text-color']['regular'];
			$sec_btn_hover_color_text = $exertio_theme_options['second-opt-theme-btn-text-color']['hover'];

			$custom_css = "
				h2,h1 { color:{$h2_color} }
				.btn-theme,  .post-excerpt .wp-block-button .wp-block-button__link, .post-excerpt .wp-block-search__button, .post-excerpt .wp-block-file .wp-block-file__button, .post-password-form input[type='submit'], .woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, .jconfirm-buttons .btn-primary, .woocommerce .exertio-my-account  .woocommerce-MyAccount-content .button { border: 1px solid $main_btn_color; background-color: $main_btn_color; color: $main_btn_color_text; }
				.btn-theme:hover, .post-excerpt .wp-block-button .wp-block-button__link:hover, .post-excerpt .wp-block-search__button:hover, .post-excerpt .wp-block-file .wp-block-file__button:hover, .post-password-form input[type='submit']:hover, .woocommerce #respond input#submit.alt:hover, .woocommerce a.button.alt:hover, .woocommerce button.button.alt:hover, .woocommerce input.button.alt:hover, .woocommerce #respond input#submit:hover, .woocommerce a.button:hover, .woocommerce button.button:hover, .woocommerce input.button:hover, .jconfirm-buttons .btn-primary:hover,.woocommerce .exertio-my-account .woocommerce-MyAccount-content .button:hover  {  background-color: $main_btn_color_hover; box-shadow: 0 0.5rem 1.125rem -0.5rem $main_btn_color_shadow; color: $main_btn_hover_color_text !important; border: 1px solid $main_btn_color_hover; }
				
				.btn-theme-secondary { border: 1px solid $sec_btn_color; background-color: $sec_btn_color; color: $sec_btn_color_text; }
				.btn-theme-secondary:hover { background-color: $sec_btn_color_hover; box-shadow: 0 0.5rem 1.125rem -0.5rem $sec_btn_color_shadow; color: $sec_btn_hover_color_text !important; border: 1px solid $sec_btn_color_hover; }
				
				
				a:hover, .fr-hero3-content span, .fr-latest-content-service span.reviews i, .fr-latest-details p span, .call-actionz .parallex-text h5, .agent-1 .card-body .username, .widget-inner-icon, .widget-inner-text .fa-star, .fr-client-sm p, .fr-latest-style ul li .fr-latest-profile i, .fr-latest-container span.readmore, .fr-browse-category .fr-browse-content ul li a.view-more, .fr-footer .fr-bottom p a, .fr-latest2-content-box .fr-latest2-price ul li p.info-in, .fr-top-contents .fr-top-details span.rating i, .fr-top-contents .fr-top-details p .style-6, .fr-h-star i, .fr-latest-jobs .owl-nav i, .project-list-2 .top-side .user-name a, .project-list-2 .bottom-side ul.features li i, .service-side .heading a, .exertio-services-box .exertio-service-desc span.rating i, .exertio-services-box .exertio-services-bottom .style-6, .exertio-services-list-2 .exertio-services-2-meta div.rating i, .exertio-services-list-2 .exertio-services-2-meta p a.author, .exertio-services-2-meta ul li span.style-6, .project-sidebar .heading a, .fr-employ-content .fr-employer-assets .btn-theme, .fr-btn-grid a, .fr3-product-price p i, .fr3-product-btn .btn, .fr3-job-detail .fr3-job-text p.price-tag, .fr3-job-detail .fr3-job-img p i, .fr-lance-content3 .fr-lance-price2 p, .fr-lance-content3 .fr-lance-detail-box .fr-lance-usr-details p i, .fr-hero-details-content .fr-hero-details-information .fr-hero-m-deails ul li:last-child i, .fr-expertise-product .fr-expertise-details span, .fr-m-products ul li p i, .fr-services2-box .fr-services2-sm-1 span, .fr-sign-bundle-content p span, .testimonial-section-fancy .details h4 + span, .fr-latest-content h3 a:hover, .fr-blog-f-details .fr-latest-style-detai ul li i, blockquote::after, .exertio-comms .comment-user .username a, .exertio-comms .comment-user .username, .sidebar .nav .nav-item.active > .nav-link i, .sidebar .nav .nav-item.active > .nav-link .menu-title, .sidebar .nav .nav-item.active > .nav-link .menu-arrow, .sidebar .nav:not(.sub-menu) > .nav-item:hover > .nav-link, .most-viewed-widget .main-price, .footer a, .navbar .navbar-menu-wrapper .navbar-nav .nav-item.dropdown .navbar-dropdown .dropdown-item.wallet-contanier h4, .pro-box .pro-meta-box .pro-meta-price span  { color:{$main_btn_color} }
				
				.sb-header .sb-menu li:not(:last-child) a:hover, .sb-header .sb-menu li:not(:last-child) a:focus, .sb-header .sb-menu li:not(:last-child) a:active, .fr-latest-content-service p a:hover, .fr-browse-category .fr-browse-content ul li a:hover, .fr-footer .fr-footer-content ul li a:hover, .fr-right-detail-box .fr-right-detail-content .fr-right-details2 h3:hover, .fr-right-detail-box .fr-right-detail-content .fr-right-details2 h3:hover, .blog-sidebar .widget ul li a:hover, .woocommerce .woocommerce-MyAccount-navigation ul li a:hover, .woocommerce .woocommerce-MyAccount-navigation ul li.is-active a { color:$main_btn_color }
				
				.sb-menu ul ul li > a::before, .exertio-loader .exertio-dot, .exertio-loader .exertio-dots span { background: $main_btn_color; }
				
				.select2-container--default .select2-results__option--highlighted[data-selected], .select2-container--default .select2-results__option--highlighted[aria-selected], .fr-hero2-video i, .exertio-pricing-2-main .exertio-pricing-price, .services-filter-2 .services-grid-icon.active, .services-filter-2 .services-list-icon.active, .fl-navigation li.active, .project-sidebar .range-slider .irs--round .irs-from, .project-sidebar .range-slider .irs--round .irs-to, .project-sidebar .range-slider .irs--round .irs-single, .project-sidebar .range-slider .irs--round .irs-bar,.fr-employ-content .fr-employer-assets .btn-theme:hover, .fr-services2-h-style h3::before, .fr-latest-t-content .fr-latest-t-box a:hover, .tagcloud a.tag-cloud-link:hover, .wp-block-tag-cloud .tag-cloud-link:hover, .page-links .current .no, .nav-pills .nav-link.active, .nav-pills .show > .nav-link, .deposit-box .depoist-header .icon, .deposit-footer button, .review-modal .modal-header { color:$main_btn_hover_color_text; background-color: $main_btn_color; }
				
				.fr-footer .fr-footer-icons ul li a i:hover, .fr-latest-pagination .pagination .page-item.active .page-link, .fr-latest-pagination .page-link:hover, .fl-search-blog .input-group .input-group-append .blog-search-btn, .card-body .pretty.p-switch input:checked ~ .state.p-info::before, .fr-sign-form label.radio input:checked + span, .user-selection-modal label.radio input:checked + span { background-color: $main_btn_color !important; border-color: $main_btn_color; }
				
				.fr-hero2-form .style-bind, .fr-hero2-video a, .fl-navigation li.active, .project-sidebar .range-slider .irs--round .irs-handle, .fr-employ-content .fr-employer-assets .btn-theme, .fr3-product-btn .btn, .slider-box .flexslider.fr-slick-thumb .flex-active-slide, .fr-plan-basics-2 .fr-plan-content button, .heading-dots .h-dot.line-dot, .heading-dots .h-dot, .post-excerpt .wp-block-button.is-style-outline .wp-block-button__link:not(.has-text-color), .pretty input:checked ~ .state.p-success-o label::before, .pretty.p-toggle .state.p-success-o label::before, .additional-fields-container .pretty.p-switch input:checked ~ .state.p-primary::before, .additional-fields-container .irs--round .irs-handle, .review-modal .button_reward .pretty.p-switch input:checked ~ .state.p-info::before  { border-color:$main_btn_color;}
				
				.pretty input:checked ~ .state.p-warning label::after, .pretty.p-toggle .state.p-warning label::after, .pretty.p-default:not(.p-fill) input:checked ~ .state.p-success-o label::after, .additional-fields-container .pretty input:checked ~ .state.p-primary label::after, .additional-fields-container .pretty input:checked ~ .state.p-primary label::after, .additional-fields-container .irs--round .irs-bar, .additional-fields-container .irs--round .irs-from, .additional-fields-container .irs--round .irs-to, .review-modal .button_reward  .pretty.p-switch.p-fill input:checked ~ .state.p-info::before { background-color: $main_btn_color !important;}
				
				.project-sidebar .range-slider .irs--round .irs-from::before, .project-sidebar .range-slider .irs--round .irs-to::before, .fr-m-contents, .additional-fields-container .irs--round .irs-to::before, .additional-fields-container .irs--round .irs-from::before  { border-top-color:$main_btn_color;}
			";
			wp_add_inline_style('theme_custom_css', $custom_css);
		}

		wp_enqueue_style('exertio-theme-typography', inline_typography(), array(), null);
	}


	if (in_array('redux-framework/redux-framework.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		global $exertio_theme_options;
		$reset = false;
		$is_reset = false;
		$user_id = $status_msg = '';

		$activation_is_key = false;
		$activation_status = false;
		$activation_status_msg = '';
		if (is_page_template('page-login.php')) {
			if (!empty($_GET['key']) && !empty($_GET['login'])) {
				$is_reset = true;
				$reset = false;
				$user = check_password_reset_key($_GET['key'], $_GET['login']);
				$errors = new WP_Error();
				if (is_wp_error($user)) {
					$reset = false;
					if ($user->get_error_code() === 'expired_key') {
						$status_msg = esc_html__('Key is expired.', 'exertio_theme');
					} else {
						$status_msg = esc_html__('Key is not valid.', 'exertio_theme');
					}
				} else {
					$reset = true;
					$user_id = $user->ID;
					$status_msg = esc_html__('Choose your password.', 'exertio_theme');
				}
			}
		}
		if (is_page_template('page-home.php') || is_page()) {
			$activation_is_key = false;
			$activation_status = false;
			if (!empty($_GET['activation_key'])) {
				$activation_is_key = true;
				// $data =  json_decode(base64_decode($_GET['activation_key']));
				// $data = unserialize(base64_decode($_GET['activation_key']));
				$data = json_decode(base64_decode($_GET['activation_key']), true);


				$code = get_user_meta($data['id'], '_user_activation_code', true);
				// verify whether the code given is the same as ours
				if (isset($code) && $code != '' && $code == $data['code']) {
					//update_user_meta($data['id'], '_exertio_account_activated', 1);
					update_user_meta($data['id'], '_user_activation_code', '');
					$activation_status_msg = esc_html__('Account Activated Successfully. Please login.', 'exertio_theme');
					$activation_status = true;

					update_user_meta($data['id'], 'is_email_verified', 1);
					$freelancer_id = get_user_meta($data['id'], 'freelancer_id', true);
					update_post_meta($freelancer_id, 'is_freelancer_email_verified', 1);

					$company_id = get_user_meta($data['id'], 'employer_id', true);
					update_post_meta($company_id, 'is_employer_email_verified', 1);
				} else {
					$activation_status_msg = esc_html__('Activation key is not correct', 'exertio_theme');
					$activation_status = false;
				}
			}
		}
		$exertio_notifications_time = isset($exertio_theme_options['exertio_notifications_time']) ? $exertio_theme_options['exertio_notifications_time'] : '';
		if ($exertio_notifications_time < 10000 && $exertio_notifications_time != '') {
			$exertio_notifications_time = 10000;
		}
		$exertio_locale = substr(get_bloginfo('language'), 0, 2);
		wp_localize_script(
			'exertio-theme',
			'localize_vars_frontend',
			array(
				'freelanceAjaxurl' => admin_url('admin-ajax.php'),
				'AreYouSure' => __('Are you sure?', 'exertio_theme'),
				'Msgconfirm' => __('Confirmation', 'exertio_theme'),
				'remove' => __('Remove', 'exertio_theme'),
				'cancel' => __('Cancel', 'exertio_theme'),
				'AccDel' => __('Delete, Anyway', 'exertio_theme'),
				'proCancel' => __('Cancel, Anyway', 'exertio_theme'),
				'confimYes' => __('Yes', 'exertio_theme'),
				'confimNo' => __('No', 'exertio_theme'),
				'awardDate' => esc_html__('Award Date', 'exertio_theme'),
				'awardName' => esc_html__('Award Name', 'exertio_theme'),
				'selectImage' => esc_html__('Image', 'exertio_theme'),
				'projectURL' => esc_html__('Project url', 'exertio_theme'),
				'projectName' => esc_html__('Project Name', 'exertio_theme'),
				'expeName' => esc_html__('Experience Title', 'exertio_theme'),
				'expeCompName' => esc_html__('Company Name', 'exertio_theme'),
				'startDate' => esc_html__('Start Date', 'exertio_theme'),
				'endDate' => esc_html__('End Date', 'exertio_theme'),
				'endDatemsg' => esc_html__('Leave it empty to set it current job', 'exertio_theme'),
				'expeDesc' => esc_html__('Description', 'exertio_theme'),
				'eduName' => esc_html__('Education Title', 'exertio_theme'),
				'eduInstName' => esc_html__('Institute Name', 'exertio_theme'),
				'eduEndDatemsg' => esc_html__('Leave it empty to set it current education', 'exertio_theme'),
				'proAdminCost' => $exertio_theme_options['project_charges'],
				'YesSure' => esc_html__('Yes, I am sure', 'exertio_theme'),
				'serviceBuy' => esc_html__('Are you sure you want to purchase this service?', 'exertio_theme'),
				'maxFaqAllowed' => $exertio_theme_options['sevices_faqs_count'],
				'maxVideoAllowed' => $exertio_theme_options['sevices_youtube_links_count'],
				'projectmaxVideoAllowed' => $exertio_theme_options['project_youtube_links_count'],
				'maxAllowedFields' => esc_html__('Allowed number of fields limit reached', 'exertio_theme'),
				'invalid_youtube_url_error' => esc_html__('Please provide valid YouTube video URL only', 'exertio_theme'),
				'faqNo' => esc_html__('FAQ No', 'exertio_theme'),
				'is_reset' => $is_reset,
				'reset_status' => array('status' => $reset, 'r_msg' => $status_msg, "requested_id" => $user_id),
				'activation_is_set' => $activation_is_key,
				'activation_is_set_msg' => array('activation_status' => $activation_status, 'status_msg' => $activation_status_msg),
				'project_search_link' => isset($exertio_theme_options['project_search_page']) ? get_the_permalink($exertio_theme_options['project_search_page']) : '',
				'services_search_link' => isset($exertio_theme_options['services_search_page']) ? get_the_permalink($exertio_theme_options['services_search_page']) : '',
				'employer_search_link' => isset($exertio_theme_options['employer_search_page']) ? get_the_permalink($exertio_theme_options['employer_search_page']) : '',
				'freelancer_search_link' => isset($exertio_theme_options['freelancer_search_page']) ? get_the_permalink($exertio_theme_options['freelancer_search_page']) : '',
				'searchTalentText' => esc_html__('Serach Talent', 'exertio_theme'),
				'searchEmpText' => esc_html__('Search Employer', 'exertio_theme'),
				'findJobText' => esc_html__('Find Job', 'exertio_theme'),
				'searchServiceText' => esc_html__('Get job done', 'exertio_theme'),
				'is_rtl' => $is_rtl,
				'exertio_local' => $exertio_locale,
				'exertio_notification' => isset($exertio_theme_options['exertio_notifications']) ? $exertio_theme_options['exertio_notifications'] : '',
				'notification_time' => $exertio_notifications_time,
				'is_User_id' => get_current_user_id(),
				'pass_textWeak' => esc_html__('Weak', 'exertio_theme'),
				'pass_textMedium' => esc_html__('Medium', 'exertio_theme'),
				'pass_textStrong' => esc_html__('Strong', 'exertio_theme'),
				'pass_textVeryStrong' => esc_html__('Very Strong', 'exertio_theme'),
			)
		);
	}
	if (is_page_template('page-profile.php')) {
		wp_enqueue_script('exertio-theme-profile', trailingslashit(get_template_directory_uri()) . 'js/custom-script-profile.js', array('jquery'), false, true);
	}
}
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	function exertio_add_woo_bootstrap_input_classes($args, $key, $value = null)
	{
		switch ($args['type']) {

			case "select":
				$args['class'][] = 'form-group';
				$args['input_class'] = array('form-control', 'input-lg');
				$args['label_class'] = array('control-label');
				$args['custom_attributes'] = array('data-plugin' => 'select2', 'data-allow-clear' => 'true', 'aria-hidden' => 'true',);
				break;

			case 'country':
				$args['class'][] = 'form-group single-country';
				$args['label_class'] = array('control-label');
				break;

			case "state":
				$args['class'][] = 'form-group';
				$args['input_class'] = array('form-control', 'input-lg');
				$args['label_class'] = array('control-label');
				$args['custom_attributes'] = array('data-plugin' => 'select2', 'data-allow-clear' => 'true', 'aria-hidden' => 'true',);
				break;
			case "password":
			case "text":
			case "email":
			case "tel":
			case "number":
				$args['class'][] = 'form-group';
				$args['input_class'] = array('form-control', 'input-lg');
				$args['label_class'] = array('control-label');
				break;
			case 'textarea':
				$args['input_class'] = array('form-control', 'input-lg');
				$args['label_class'] = array('control-label');
				break;

			case 'checkbox':
				break;

			case 'radio':
				break;

			default:
				$args['class'][] = 'form-group';
				$args['input_class'] = array('form-control', 'input-lg');
				$args['label_class'] = array('control-label');
				break;
		}
		return $args;
	}
	add_filter('woocommerce_form_field_args', 'exertio_add_woo_bootstrap_input_classes', 10, 3);
	add_filter('woocommerce_single_product_carousel_options', 'exertio_woo_flexslider_options');
	function exertio_woo_flexslider_options($options)
	{
		$options['directionNav'] = true;
		return $options;
	}
}
function exertio_myme_types($mime_types)
{
	$mime_types['svg'] = 'image/svg+xml';
	$mime_types['pdf'] = 'application/pdf';
	$mime_types['doc'] = 'application/msword';
	$mime_types['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
	$mime_types['ppt'] = 'application/mspowerpoint, application/powerpoint, application/vnd.ms-powerpoint, application/x-mspowerpoint';
	$mime_types['pptx'] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
	$mime_types['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
	$mime_types['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
	$mime_types['xls|xlsx'] = 'application/vnd.ms-excel';
	return $mime_types;
}
add_filter('upload_mimes', 'exertio_myme_types', 1, 1);

add_action('delete_user', 'exertio_delete_user_data');
function exertio_delete_user_data($user_id)
{
	$args = array(
		'numberposts' => -1,
		'post_type' => array('freelancer', 'employer'),
		'author' => $user_id
	);

	$user_posts = get_posts($args);

	if (empty($user_posts)) return;

	// delete all the user posts
	foreach ($user_posts as $user_post) {
		wp_delete_post($user_post->ID, true);
	}
}


/*FOR ELEMENTOR HEADER FOOTER*/
if (in_array('elementor-pro/elementor-pro.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	add_action('elementor/theme/register_locations', 'exertio_pro_register_elementor_locations');
	function exertio_pro_register_elementor_locations($elementor_theme_manager)
	{
		$elementor_theme_manager->register_location('header');
		$elementor_theme_manager->register_location('footer');
	}
}

function exertio_tiny_mce_allowed_tags($initArray)
{
	$ext = 'p';
	if (isset($initArray['extended_valid_elements'])) {
		$initArray['extended_valid_elements'] .= ',' . $ext;
	} else {
		$initArray['extended_valid_elements'] = $ext;
	}
	return $initArray;
}

//add_filter('tiny_mce_before_init', 'exertio_tiny_mce_allowed_tags');

function exertio_widgets_block_editor_support()
{
	remove_theme_support('widgets-block-editor');
}
add_action('after_setup_theme', 'exertio_widgets_block_editor_support');

function get_current_user_id_new()
{
	$uid = get_current_user_id();
	$employer_id = get_user_meta($uid, 'employer_id', true);
	$freelancer_id = get_user_meta($uid, 'freelancer_id', true);
	if (is_super_admin($uid) && $freelancer_id == "" && $employer_id == "") {
		exertio_register_type_return($uid, 'both');
	}
}
add_action('init', 'get_current_user_id_new');
add_action('wp_footer', 'exertio_footer_function');

if (!function_exists('exertio_footer_function')) {

	function exertio_footer_function()
	{

		global $exertio_theme_options;
		if (is_page_template('page-login.php')) {
			get_template_part('template-parts/auth/password', 'reset');
		}
		if (is_singular('freelancer') || is_singular('services') || is_singular('projects') || is_singular('employer')) {
			get_template_part('template-parts/auth/report', '');
		}
		if (is_singular('freelancer')) {
			get_template_part('template-parts/auth/hire-freelancer-modal', '');
		}

		if (is_page_template('page-profile.php')) {
		} else if (is_page_template('page-login.php') && $exertio_theme_options['login_footer_show'] == 0) {
		} else if (is_page_template('page-register.php') && $exertio_theme_options['register_footer_show'] == 0) {
		} else {

			if (is_page_template('template-whizzchat.php')) {
				return;
			}
			if (isset($exertio_theme_options['footer_type'])) {
				$footer_type  = $exertio_theme_options['footer_type'];
			} else {
				$footer_type  = 0;
			}
			
			// if (isset($exertio_theme_options['footer_layout'])) {
			// 	$footer_layout  = $exertio_theme_options['footer_layout'];
			// } else {
			// 	$footer_layout  = 1;
			// }

			$custom_footer_layout = '';
			if (is_singular('page')) {
				$custom_footer_layout = get_post_meta(get_queried_object_id(), '_exertio_custom_footer', true);
			}

			if (!empty($custom_footer_layout)) {
				$footer_layout = $custom_footer_layout;
			} else {
				$footer_layout = isset($exertio_theme_options['footer_layout']) ? $exertio_theme_options['footer_layout'] : 1;
			}

			if ($footer_type  ==  1 && in_array('elementor-pro/elementor-pro.php', apply_filters('active_plugins', get_option('active_plugins')))) {
				elementor_theme_do_location('footer');
			} else {
				get_template_part('template-parts/footer/footer', $footer_layout);
			}
		}


		if (in_array('redux-framework/redux-framework.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			if (is_page_template('page-profile.php') && $exertio_theme_options['zoom_meeting_btn'] == 1) {
?>
				<div id="zoom_meeting_container"></div>
		<?php
			}
		}

		get_template_part('template-parts/verification', 'logic');
		if (isset($exertio_theme_options['job_alerts_switch']) && $exertio_theme_options['job_alerts_switch'] != ""  && $exertio_theme_options['job_alerts_switch'] == true) {
			get_template_part('template-parts/job', 'alerts');
		}

		?>
		<input type="hidden" id="freelance_ajax_url" value="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>" />
		<input type="hidden" id="gen_nonce" value="<?php echo esc_attr(wp_create_nonce('fl_gen_secure')); ?>" />
		<input type="hidden" id="nonce_error" value="<?php echo esc_attr__('Something went wrong', 'exertio_theme'); ?>" />

	<?php }
}


/* * ***************************************** */
/* Function for Creating the DataBase Table for Custom Offers(Services) */
/* * **************************************** */
function create_custom_table()
{

	if (!defined('EXERTIO_CUSTOM_OFFER_TBL')) {
		return; // Exit the function if the constant is not defined
	}
	global $wpdb;
	$table_name = EXERTIO_CUSTOM_OFFER_TBL;

	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
			freelancer_id mediumint(9) NOT NULL, 
			employer_id mediumint(9) NOT NULL, 
            receiver_id mediumint(9) NOT NULL,
			seller_id mediumint(9) NOT NULL,
			service_id mediumint(9) NOT NULL,	
            price float(9) NOT NULL,
			response_time varchar(20) NOT NULL,
			term_id mediumint(9),
			description text NOT NULL,
			status varchar(20) NOT NULL,
			stored_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, 
            PRIMARY KEY  (id)
        ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}
add_action('init', 'create_custom_table');

function display_custom_button()
{
	$update_successful = get_option('update_database_success', false);
	if (!$update_successful) {
	?>
		<div class="notice notice-success is-dismissible">
			<a class="button button-primary" id="custom-dashboard-button" href="#"><?php echo esc_html__('Click Here To update the DataBase', 'exertio_theme'); ?></a>
		</div>
	<?php
	}
}

add_action('admin_notices', 'display_custom_button');



// ***************************************************************
// -------------- Update database columns data type --------------
// ***************************************************************



if (class_exists('fl_db_tables')) {

	function exertio_update_column_data_types()
	{
		global $wpdb;

		$tables_columns = array(
			EXERTIO_PROJECT_BIDS_TBL => array(
				'proposed_cost' => 'FLOAT(10,2)',
			),
			EXERTIO_PROJECT_OFFER_TBL => array(
				'proposed_cost' => 'FLOAT(10,2)',
			),
			EXERTIO_PURCHASED_SERVICES_TBL => array(
				'total_price' => 'FLOAT(10,2)',
				'service_price' => 'FLOAT(10,2)',
				'addon_price' => 'FLOAT(10,2)',
			),
			EXERTIO_PROJECT_LOGS_TBL => array(
				'project_cost' => 'FLOAT(10,2)',
				'proposal_cost' => 'FLOAT(10,2)',
				'commission_percent' => 'FLOAT(5,2)',
			),
			EXERTIO_SERVICE_LOGS_TBL => array(
				'total_service_cost' => 'FLOAT(10,2)',
				'addons_cost' => 'FLOAT(10,2)',
				'commission_percent' => 'FLOAT(5,2)',
			),
			EXERTIO_PURCHASED_SERVICES_TBL => array(
				// New columns
				'custom_offers' => 'INT(11)',
				'extended_days' => 'INT(11)',
				'extended_status' => 'VARCHAR(100)',
				'extended_time' => 'datetime',
			),
		);

		foreach ($tables_columns as $table => $columns) {
			$table = esc_sql($table);

			// Check if the table exists
			if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
				continue;
			}

			foreach ($columns as $column => $new_type) {
				$column = esc_sql($column);
				$column_info = $wpdb->get_row("SHOW COLUMNS FROM `$table` LIKE '$column'");

				if (!$column_info) {
					// Column does not exist, so add it
					$wpdb->query("ALTER TABLE `$table` ADD `$column` $new_type NULL DEFAULT NULL");
				} elseif ($column_info->Type !== strtolower($new_type)) {
					// Column exists but has wrong type, so modify it
					$wpdb->query("ALTER TABLE `$table` MODIFY COLUMN `$column` $new_type");
				}
			}
		}
	}

	// Function to handle both new install and update cases
	function exertio_maybe_update_on_theme_update()
	{
		$theme_version = wp_get_theme()->get('Version');
		$saved_version = get_option('exertio_theme_version');

		if ($theme_version !== $saved_version) {
			// Update column types
			exertio_update_column_data_types();

			// Update the version in the database
			update_option('exertio_theme_version', $theme_version);
		}
	}

	// Run on theme activation for new users
	add_action('after_switch_theme', 'exertio_maybe_update_on_theme_update');
	// Run after theme setup
	add_action('after_setup_theme', 'exertio_maybe_update_on_theme_update');
	// Run on admin_init to catch theme updates for existing users
	add_action('admin_init', 'exertio_maybe_update_on_theme_update');
}



// =========== styling woocommerce pages (cart/checkout) ================


function add_custom_classes_to_woocommerce_blocks( $block_content, $block ) {
	if ( ! isset( $block['blockName'] ) ) {
		return $block_content;
	}

	$custom_classes = 'container mx-auto py-5';

	// For WooCommerce Checkout block
	if ( $block['blockName'] === 'woocommerce/checkout' ) {
		$block_content = str_replace(
			'class="wp-block-woocommerce-checkout',
			'class="wp-block-woocommerce-checkout ' . $custom_classes,
			$block_content
		);
	}

	// For WooCommerce Cart block
	if ( $block['blockName'] === 'woocommerce/cart' ) {
		$block_content = str_replace(
			'class="wp-block-woocommerce-cart',
			'class="wp-block-woocommerce-cart ' . $custom_classes,
			$block_content
		);
	}

	return $block_content;
}
add_filter( 'render_block', 'add_custom_classes_to_woocommerce_blocks', 10, 2 );
/**
 * Functions/snippet completo do fluxo RMA para colar no functions.php.
 *
 * O que entrega:
 * - Enqueue do UI kit + fonte Federo
 * - Shortcode [rma_glass_card_demo]
 * - Shortcode [rma_conta_setup] com formulário atualizado (novos inputs)
 * - Shortcode [rma-minha-conta] para edição cadastral da entidade
 * - Checklist de documentos (PDF, imagem ou Word) com tooltip + upload
 * - Redirect forçado para /conta/ até concluir governança + docs + financeiro
 */

if (! defined('ABSPATH')) {
    exit;
}

function rma_ui_theme_base() {
    $child_path = trailingslashit(get_stylesheet_directory()) . 'ui';
    if (is_dir($child_path)) {
        return [
            'uri'  => trailingslashit(get_stylesheet_directory_uri()) . 'ui',
            'path' => $child_path,
        ];
    }

    $parent_path = trailingslashit(get_template_directory()) . 'ui';
    if (is_dir($parent_path)) {
        return [
            'uri'  => trailingslashit(get_template_directory_uri()) . 'ui',
            'path' => $parent_path,
        ];
    }

    return null;
}

function rma_account_setup_url() {
    return trailingslashit(home_url('/conta/'));
}

function rma_account_setup_path() {
    $path = wp_parse_url(rma_account_setup_url(), PHP_URL_PATH);
    return is_string($path) ? untrailingslashit($path) : '';
}

function rma_build_onboarding_step_url(string $step = 'identity', array $extra_args = []): string {
    $args = array_merge([
        'rma_step' => sanitize_key($step ?: 'identity'),
    ], $extra_args);

    return add_query_arg($args, rma_account_setup_url());
}

function rma_get_onboarding_resume_url(int $user_id): string {
    if ($user_id <= 0) {
        return rma_build_onboarding_step_url('setup');
    }

    $entity_id = rma_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        return rma_build_onboarding_step_url('setup');
    }

    if (! rma_is_2fa_verified($user_id)) {
        return rma_build_onboarding_step_url('identity');
    }

    $governance = strtolower(trim((string) get_post_meta($entity_id, 'governance_status', true)));
    $finance = strtolower(trim((string) get_post_meta($entity_id, 'finance_status', true)));
    $docs_status = strtolower(trim((string) get_post_meta($entity_id, 'documentos_status', true)));
    $rejected_document_types = get_post_meta($entity_id, 'documentos_reprovados', true);
    $rejected_document_types = is_array($rejected_document_types) ? array_values(array_filter(array_map('sanitize_key', $rejected_document_types))) : [];
    $uploaded_document_types = rma_get_entity_uploaded_document_types($entity_id);
    $required_document_types = ['ficha_inscricao', 'comprovante_cnpj', 'ata_fundacao', 'ata_diretoria', 'estatuto', 'relatorio_atividades', 'cartas_recomendacao'];
    $missing_document_types = array_values(array_diff($required_document_types, $uploaded_document_types));
    $docs_approved = in_array($docs_status, ['aprovado', 'validado', 'aceito'], true) || $governance === 'aprovado';

    if (! empty($missing_document_types) || ! empty($rejected_document_types) || in_array($docs_status, ['pendente', 'rejeitado', 'negado', 'pendente_reenvio', 'correcao', 'reprovado'], true)) {
        return rma_build_onboarding_step_url('documentos');
    }

    if (! $docs_approved) {
        return rma_build_onboarding_step_url('validacao');
    }

    if ($finance !== 'adimplente') {
        return rma_build_onboarding_step_url('pagamento');
    }

    if (! rma_entity_has_uploaded_logo($entity_id, $user_id)) {
        return add_query_arg([
            'ext' => 'edit-profile',
            'rma_logo_required' => '1',
        ], home_url('/dashboard/'));
    }

    return home_url('/dashboard/');
}

function rma_is_allowed_onboarding_resume_url(string $url): bool {
    if ($url === '') {
        return false;
    }

    $home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    $url_host = (string) wp_parse_url($url, PHP_URL_HOST);
    if ($home_host !== '' && $url_host !== '' && ! hash_equals($home_host, $url_host)) {
        return false;
    }

    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $path = untrailingslashit($path);
    if ($path === '') {
        return false;
    }

    $allowed_paths = array_filter([
        rma_account_setup_path(),
        untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH)),
        untrailingslashit((string) wp_parse_url(apply_filters('rma_checkout_url', home_url('/checkout/')), PHP_URL_PATH)),
    ]);

    return in_array($path, $allowed_paths, true);
}

function rma_get_current_request_url(): string {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($request_uri === '') {
        return '';
    }

    return home_url($request_uri);
}


function rma_theme_image_uri($filename) {
    $filename = ltrim((string) $filename, '/');
    return trailingslashit(get_template_directory_uri()) . 'images/' . $filename;
}

function rma_get_entity_id_by_author($user_id) {
    $query = new WP_Query([
        'post_type'              => 'rma_entidade',
        'post_status'            => ['publish', 'draft', 'pending', 'private'],
        'author'                 => (int) $user_id,
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $entity_id = ! empty($query->posts) ? (int) $query->posts[0] : 0;
    wp_reset_postdata();

    return $entity_id;
}

function rma_get_dashboard_profile_post_id_by_author(int $user_id): int {
    if ($user_id <= 0) {
        return 0;
    }

    $query = new WP_Query([
        'post_type'              => 'employer',
        'post_status'            => ['publish', 'draft', 'pending', 'private'],
        'author'                 => $user_id,
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $post_id = ! empty($query->posts) ? (int) $query->posts[0] : 0;
    wp_reset_postdata();

    return $post_id;
}

function rma_theme_entity_has_started_or_completed_dues_checkout(int $entity_id): bool {
    if ($entity_id <= 0 || ! function_exists('wc_get_orders')) {
        return false;
    }

    $orders = wc_get_orders([
        'limit' => 1,
        'return' => 'ids',
        'meta_query' => [
            [
                'key' => 'rma_entity_id',
                'value' => $entity_id,
            ],
            [
                'key' => 'rma_is_annual_due',
                'value' => '1',
            ],
        ],
        'status' => ['pending', 'on-hold', 'processing', 'completed'],
    ]);

    return ! empty($orders);
}

function rma_entity_has_completed_activation_flow(int $entity_id): bool {
    if ($entity_id <= 0) {
        return false;
    }

    $governance = (string) get_post_meta($entity_id, 'governance_status', true);
    $finance = (string) get_post_meta($entity_id, 'finance_status', true);
    $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);

    return $governance === 'aprovado'
        && $finance === 'adimplente'
        && in_array($docs_status, ['enviado', 'aprovado', 'validado', 'aceito'], true);
}

function rma_is_valid_logo_attachment(int $attachment_id): bool {
    if ($attachment_id <= 0 || ! wp_attachment_is_image($attachment_id)) {
        return false;
    }

    $url = (string) wp_get_attachment_url($attachment_id);
    $file = strtolower((string) basename((string) $url));
    $blocked_files = ['emp_default.jpg', 'emp_default.jpeg', 'emp_default.png', 'default-avatar.png', 'default.jpg'];

    if ($file !== '' && in_array($file, $blocked_files, true)) {
        return false;
    }

    if (strpos($url, 'emp_default') !== false || strpos($url, 'default-avatar') !== false) {
        return false;
    }

    return true;
}

function rma_entity_has_uploaded_logo(int $entity_id, int $user_id): bool {
    $attachment_id = (int) get_post_meta($entity_id, '_profile_pic_attachment_id', true);
    if (rma_is_valid_logo_attachment($attachment_id)) {
        return true;
    }

    $profile_post_id = rma_get_dashboard_profile_post_id_by_author($user_id);
    if ($profile_post_id > 0) {
        $profile_attachment_id = (int) get_post_meta($profile_post_id, '_profile_pic_attachment_id', true);
        if (rma_is_valid_logo_attachment($profile_attachment_id)) {
            return true;
        }
    }

    return false;
}

function rma_get_entity_uploaded_document_types(int $entity_id): array {
    if ($entity_id <= 0) {
        return [];
    }

    $documents = get_post_meta($entity_id, 'entity_documents', true);
    if (! is_array($documents) || empty($documents)) {
        return [];
    }

    $document_types = [];
    foreach ($documents as $document) {
        $document_type = sanitize_key((string) ($document['document_type'] ?? ''));
        if ($document_type !== '') {
            $document_types[] = $document_type;
        }
    }

    return array_values(array_unique($document_types));
}

function rma_theme_get_latest_dues_order_snapshot(int $entity_id): array {
    if ($entity_id <= 0 || ! function_exists('wc_get_orders')) {
        return [];
    }

    $orders = wc_get_orders([
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'objects',
        'meta_query' => [
            [
                'key' => 'rma_entity_id',
                'value' => $entity_id,
            ],
            [
                'key' => 'rma_is_annual_due',
                'value' => '1',
            ],
        ],
        'status' => ['pending', 'on-hold', 'processing', 'completed'],
    ]);

    $order = $orders[0] ?? null;
    if (! $order || ! is_object($order) || ! method_exists($order, 'get_status')) {
        return [];
    }

    return [
        'id' => (int) $order->get_id(),
        'status' => (string) $order->get_status(),
        'payment_url' => method_exists($order, 'get_checkout_payment_url') ? (string) $order->get_checkout_payment_url() : '',
    ];
}

function rma_flow_debug_enabled() {
    if (defined('RMA_FLOW_DEBUG') && RMA_FLOW_DEBUG) {
        return true;
    }

    if (isset($_GET['rma_debug_flow']) && sanitize_text_field((string) $_GET['rma_debug_flow']) === '1') {
        return true;
    }

    return (bool) get_option('rma_flow_debug_enabled', false);
}

function rma_flow_debug_log($message, array $context = []) {
    if (! rma_flow_debug_enabled()) {
        return;
    }

    if (! empty($context)) {
        $message .= ' | ' . wp_json_encode($context);
    }

    error_log('[RMA_FLOW] ' . $message);
}


function rma_session_bootstrap(): void {
    if (headers_sent()) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}
add_action('init', 'rma_session_bootstrap', 1);

function rma_mark_2fa_verified(int $user_id): void {
    rma_session_bootstrap();
    $_SESSION['rma_2fa_verified'] = true;
    $_SESSION['rma_2fa_verified_expires_at'] = time() + (30 * MINUTE_IN_SECONDS);
    update_user_meta($user_id, 'rma_otp_verified_at', time());
}



function rma_clear_2fa_verification_state(?int $user_id = null): void {
    rma_session_bootstrap();

    unset($_SESSION['rma_2fa_verified'], $_SESSION['rma_2fa_verified_expires_at']);

    $target_user_id = $user_id ?: get_current_user_id();
    if ($target_user_id > 0) {
        delete_user_meta($target_user_id, 'rma_otp_verified_at');
    }
}

add_action('wp_logout', function () {
    $user_id = get_current_user_id();
    rma_clear_2fa_verification_state($user_id > 0 ? (int) $user_id : null);
});

add_action('wp_login', function ($user_login, $user) {
    if ($user instanceof WP_User) {
        // Garante sessão limpa para novo login e força 2FA por sessão.
        rma_clear_2fa_verification_state((int) $user->ID);
    }
}, 10, 2);

function rma_is_2fa_verified(int $user_id): bool {
    rma_session_bootstrap();

    if (isset($_SESSION['rma_2fa_verified'], $_SESSION['rma_2fa_verified_expires_at']) && $_SESSION['rma_2fa_verified'] === true) {
        $expires_at = (int) $_SESSION['rma_2fa_verified_expires_at'];
        if ($expires_at > time()) {
            return true;
        }
    }

    $verified_at = (int) get_user_meta($user_id, 'rma_otp_verified_at', true);
    if ($verified_at > 0 && ($verified_at + (30 * MINUTE_IN_SECONDS)) > time()) {
        $_SESSION['rma_2fa_verified'] = true;
        $_SESSION['rma_2fa_verified_expires_at'] = $verified_at + (30 * MINUTE_IN_SECONDS);
        return true;
    }

    unset($_SESSION['rma_2fa_verified'], $_SESSION['rma_2fa_verified_expires_at']);
    return false;
}


function rma_otp_transient_key(int $user_id): string {
    return 'rma_otp_code_' . $user_id;
}

function rma_otp_send_lock_key(int $user_id): string {
    return 'rma_otp_send_lock_' . $user_id;
}

function rma_get_otp_delivery_email(int $user_id): string {
    if ($user_id <= 0) {
        return '';
    }

    $entity_id = rma_get_entity_id_by_author($user_id);
    if ($entity_id > 0) {
        $entity_email = sanitize_email((string) get_post_meta($entity_id, 'email_contato', true));
        if ($entity_email !== '' && is_email($entity_email)) {
            return $entity_email;
        }
    }

    $user = get_user_by('id', $user_id);
    if (! $user instanceof WP_User) {
        return '';
    }

    $user_email = sanitize_email((string) $user->user_email);
    return ($user_email !== '' && is_email($user_email)) ? $user_email : '';
}

function rma_mask_email_for_display(string $email): string {
    $email = trim($email);
    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }

    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') {
        return '';
    }

    $local_visible = strlen($local) <= 2
        ? substr($local, 0, 1)
        : (substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1));

    $domain_parts = explode('.', $domain);
    $domain_name = (string) array_shift($domain_parts);
    $domain_tld = ! empty($domain_parts) ? '.' . implode('.', $domain_parts) : '';
    $domain_visible = strlen($domain_name) <= 2
        ? substr($domain_name, 0, 1)
        : (substr($domain_name, 0, 1) . str_repeat('*', max(1, strlen($domain_name) - 2)) . substr($domain_name, -1));

    return $local_visible . '@' . $domain_visible . $domain_tld;
}

function rma_otp_error_status(WP_Error $error): int {
    switch ($error->get_error_code()) {
        case 'rma_otp_rate_limited':
            return 429;
        case 'rma_otp_user_invalid':
        case 'rma_otp_missing_delivery_email':
            return 422;
        default:
            return 503;
    }
}

function rma_send_security_email(string $to, string $subject, string $html_message): bool {
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $sender_mode = (string) get_option('rma_email_sender_mode', 'wp_mail');

    if ($sender_mode === 'woo_mail' && function_exists('WC') && WC() && method_exists(WC(), 'mailer')) {
        $mailer = WC()->mailer();
        if ($mailer && method_exists($mailer, 'send')) {
            // Não usar wrap_message para OTP: manter layout custom sem o cabeçalho/container padrão do Woo.
            return (bool) $mailer->send($to, $subject, $html_message, $headers, []);
        }
    }

    return (bool) wp_mail($to, $subject, $html_message, $headers);
}

function rma_render_onboarding_continue_email(array $context): string {
    $logo_file = trailingslashit(get_template_directory()) . 'images/rma-logo-white.png';
    $logo_html = '';
    if (file_exists($logo_file)) {
        $logo_html = '<img src="' . esc_url(rma_theme_image_uri('rma-logo-white.png')) . '" alt="RMA" style="max-width:190px;width:100%;height:auto;margin:0 auto 18px;display:block;" />';
    }
    $headline = esc_html($context['headline'] ?? 'Bem-vindo à RMA');
    $greeting = esc_html($context['greeting'] ?? 'Olá,');
    $intro = esc_html($context['intro'] ?? 'Você iniciou seu cadastro na RMA.');
    $button_label = esc_html($context['button_label'] ?? 'Continuar cadastro');
    $button_url = esc_url($context['button_url'] ?? rma_build_onboarding_step_url('identity'));
    $user_name = esc_html($context['user_name'] ?? '');
    $user_email = esc_html($context['user_email'] ?? '');

    return '<div style="margin:0;padding:24px;background:#f3f6fb;font-family:Arial,sans-serif;color:#1f2937;">'
        . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 18px 48px rgba(15,23,42,.08);">'
        . '<div style="padding:32px 28px;background:linear-gradient(135deg,#7bad39,#5ddabb);text-align:center;">'
        . $logo_html
        . '<h1 style="margin:0;color:#ffffff;font-size:42px;line-height:1.1;font-weight:700;">' . $headline . '</h1>'
        . '</div>'
        . '<div style="padding:34px 40px;">'
        . '<p style="margin:0 0 18px;font-size:18px;line-height:1.6;">' . $greeting . '</p>'
        . '<p style="margin:0 0 22px;font-size:18px;line-height:1.7;">' . $intro . ' Para dar continuidade ao processo de ativação institucional, retome sua jornada pelo botão abaixo.</p>'
        . '<div style="margin:0 0 24px;padding:22px 24px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;">'
        . '<p style="margin:0 0 8px;font-size:16px;line-height:1.5;"><strong>Nome de Usuário:</strong> ' . $user_name . '</p>'
        . '<p style="margin:0;font-size:16px;line-height:1.5;"><strong>E-mail:</strong> ' . $user_email . '</p>'
        . '</div>'
        . '<div style="margin:0 0 26px;text-align:center;">'
        . '<a href="' . $button_url . '" style="display:inline-block;padding:15px 26px;border-radius:999px;background:linear-gradient(135deg,#7bad39,#5ddabb);color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;">' . $button_label . '</a>'
        . '</div>'
        . '<p style="margin:0 0 20px;font-size:16px;line-height:1.7;">Se você tiver interrompido o processo em qualquer etapa, esse botão levará você exatamente ao ponto de continuidade do seu cadastro.</p>'
        . '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Se tiver qualquer dúvida, basta responder a este e-mail. Teremos prazer em ajudar.</p>'
        . '<p style="margin:0;font-size:16px;line-height:1.7;">Atenciosamente,<br><strong>Equipe RMA</strong></p>'
        . '</div>'
        . '</div>'
        . '</div>';
}

add_action('rma/entity_created', function (int $entity_id, array $params = []): void {
    $user_id = (int) get_post_field('post_author', $entity_id);
    if ($user_id <= 0) {
        return;
    }

    if ((int) get_post_meta($entity_id, 'rma_onboarding_continue_email_sent', true) === 1) {
        return;
    }

    $delivery_email = rma_get_otp_delivery_email($user_id);
    if ($delivery_email === '') {
        return;
    }

    $user = get_user_by('id', $user_id);
    $user_name = $user instanceof WP_User ? (string) $user->display_name : (string) ($params['nome_fantasia'] ?? '');
    $resume_url = rma_build_onboarding_step_url('identity', ['rma_notice' => 'entity-created']);
    update_user_meta($user_id, 'rma_last_onboarding_url', $resume_url);
    update_user_meta($user_id, 'rma_onboarding_started', 1);

    $subject = 'Continue seu cadastro na RMA';
    $message = rma_render_onboarding_continue_email([
        'headline' => 'Bem-vindo à RMA',
        'greeting' => 'Olá, ' . ($user_name !== '' ? $user_name : ''),
        'intro' => 'Você iniciou seu cadastro na RMA.',
        'button_label' => 'Continuar cadastro',
        'button_url' => $resume_url,
        'user_name' => $user_name,
        'user_email' => $delivery_email,
    ]);

    if (rma_send_security_email($delivery_email, $subject, $message)) {
        update_post_meta($entity_id, 'rma_onboarding_continue_email_sent', 1);
    }
}, 10, 2);

add_action('template_redirect', function (): void {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || ! is_user_logged_in()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $request_path = untrailingslashit($request_path);
    if ($request_path === '') {
        return;
    }

    $account_path = rma_account_setup_path();
    $checkout_path = untrailingslashit((string) wp_parse_url(apply_filters('rma_checkout_url', home_url('/checkout/')), PHP_URL_PATH));
    $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));

    if (! in_array($request_path, array_filter([$account_path, $checkout_path, $dashboard_path]), true)) {
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    update_user_meta($user_id, 'rma_last_onboarding_url', rma_get_current_request_url());
    update_user_meta($user_id, 'rma_onboarding_started', 1);
}, 5);

add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {
    if (! $user instanceof WP_User) {
        return $redirect_to;
    }

    if (user_can($user, 'manage_options') || user_can($user, 'edit_others_posts')) {
        return $redirect_to;
    }

    $has_entity = rma_get_entity_id_by_author((int) $user->ID) > 0;
    $onboarding_started = (int) get_user_meta($user->ID, 'rma_onboarding_started', true) === 1;
    $resume_url = (string) get_user_meta($user->ID, 'rma_last_onboarding_url', true);
    if (! $has_entity && ! $onboarding_started && $resume_url === '') {
        return $redirect_to;
    }

    if ($resume_url !== '' && rma_is_allowed_onboarding_resume_url($resume_url)) {
        return $resume_url;
    }

    return rma_get_onboarding_resume_url((int) $user->ID);
}, 20, 3);

function rma_send_otp_code_for_user(int $user_id) {
    $user = get_user_by('id', $user_id);
    if (! $user instanceof WP_User) {
        return new WP_Error('rma_otp_user_invalid', 'Usuário inválido para verificação.');
    }

    if (get_transient(rma_otp_send_lock_key($user_id))) {
        return new WP_Error('rma_otp_rate_limited', 'Aguarde alguns segundos antes de solicitar um novo código.');
    }

    set_transient(rma_otp_send_lock_key($user_id), '1', 30);

    $delivery_email = rma_get_otp_delivery_email($user_id);
    if ($delivery_email === '') {
        delete_transient(rma_otp_send_lock_key($user_id));
        return new WP_Error('rma_otp_missing_delivery_email', 'Não encontramos um email válido para enviar o código de verificação.');
    }

    $code = (string) wp_rand(100000, 999999);
    $payload = [
        'code' => $code,
        'expires_at' => time() + (10 * MINUTE_IN_SECONDS),
        'attempts' => 0,
    ];
    set_transient(rma_otp_transient_key($user_id), $payload, 10 * MINUTE_IN_SECONDS);

    $subject = 'Código de verificação de segurança - RMA';
    $message = function_exists('rma_render_verification_email_template')
        ? rma_render_verification_email_template([
            'nome' => (string) $user->display_name,
            'codigo' => $code,
            'data' => wp_date('d/m/Y H:i'),
            'empresa' => (string) get_option('rma_email_verification_company', 'RMA'),
        ])
        : ('Seu código de verificação é: ' . $code);

    $sent = rma_send_security_email($delivery_email, $subject, (string) $message);

    if (! $sent) {
        delete_transient(rma_otp_transient_key($user_id));
        delete_transient(rma_otp_send_lock_key($user_id));
        return new WP_Error('rma_otp_send_failed', 'Não foi possível enviar o código no momento. Tente novamente ou verifique sua caixa de spam.');
    }

    return [
        'sent' => true,
        'delivery_email' => $delivery_email,
        'delivery_masked' => rma_mask_email_for_display($delivery_email),
    ];
}

add_action('rest_api_init', function () {
    register_rest_route('rma/v1', '/otp/send', [
        'methods' => 'POST',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback' => function () {
            $result = rma_send_otp_code_for_user(get_current_user_id());
            if (is_wp_error($result)) {
                return new WP_REST_Response(['message' => $result->get_error_message()], rma_otp_error_status($result));
            }

            $delivery_masked = (string) ($result['delivery_masked'] ?? '');
            $message = $delivery_masked !== ''
                ? ('Código enviado para ' . $delivery_masked . '.')
                : 'Código enviado para seu email institucional.';

            return new WP_REST_Response([
                'sent' => true,
                'message' => $message,
                'delivery_masked' => $delivery_masked,
            ]);
        },
    ]);


    register_rest_route('rma/v1', '/otp/status', [
        'methods' => 'GET',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback' => function () {
            $user_id = get_current_user_id();
            $is_valid = rma_is_2fa_verified($user_id);
            $verified_at = (int) get_user_meta($user_id, 'rma_otp_verified_at', true);
            $valid_until = $verified_at > 0 ? ($verified_at + (30 * MINUTE_IN_SECONDS)) : 0;
            $delivery_email = rma_get_otp_delivery_email($user_id);
            return new WP_REST_Response([
                'verified' => $is_valid,
                'verified_at' => $verified_at,
                'valid_until' => $valid_until,
                'delivery_masked' => rma_mask_email_for_display($delivery_email),
                'has_delivery_email' => ($delivery_email !== ''),
            ]);
        },
    ]);



    // Compat route para evitar 404 em ambientes com JS legado apontando para /onboarding/status.
    // Evita re-registro quando o plugin rma-core-entities já expõe a mesma rota.
    $routes = rest_get_server()->get_routes();
    if (! isset($routes['/rma/v1/onboarding/status'])) {
        register_rest_route('rma/v1', '/onboarding/status', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'callback' => function () {
                $user_id = get_current_user_id();
                $entity_id = rma_get_entity_id_by_author($user_id);
                if ($entity_id <= 0) {
                    return new WP_REST_Response([
                        'entity_id' => 0,
                        'governance_status' => 'pendente',
                        'finance_status' => 'pendente',
                        'documentos_status' => 'pendente',
                        'rejected_document_types' => [],
                    ]);
                }

                return new WP_REST_Response([
                    'entity_id' => $entity_id,
                    'governance_status' => (string) get_post_meta($entity_id, 'governance_status', true),
                    'finance_status' => (string) get_post_meta($entity_id, 'finance_status', true),
                    'documentos_status' => (string) get_post_meta($entity_id, 'documentos_status', true),
                    'rejected_document_types' => array_values(array_filter(array_map('sanitize_key', (array) get_post_meta($entity_id, 'documentos_reprovados', true)))),
                ]);
            },
        ]);
    }

    register_rest_route('rma/v1', '/otp/verify', [
        'methods' => 'POST',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback' => function (WP_REST_Request $request) {
            $user_id = get_current_user_id();
            $code = preg_replace('/\D+/', '', (string) $request->get_param('code'));

            if (strlen($code) !== 6) {
                return new WP_REST_Response(['verified' => false, 'message' => 'Código inválido. Digite os 6 dígitos enviados por email.'], 422);
            }

            $payload = get_transient(rma_otp_transient_key($user_id));
            if (! is_array($payload) || empty($payload['code'])) {
                return new WP_REST_Response(['verified' => false, 'message' => 'Código expirado. Solicite um novo envio.'], 410);
            }

            $attempts = (int) ($payload['attempts'] ?? 0) + 1;
            $payload['attempts'] = $attempts;
            set_transient(rma_otp_transient_key($user_id), $payload, max(30, ((int) ($payload['expires_at'] ?? time())) - time()));

            if ($attempts > 5) {
                delete_transient(rma_otp_transient_key($user_id));
                return new WP_REST_Response(['verified' => false, 'message' => 'Muitas tentativas inválidas. Solicite novo código.'], 429);
            }

            if ((int) ($payload['expires_at'] ?? 0) < time()) {
                delete_transient(rma_otp_transient_key($user_id));
                return new WP_REST_Response(['verified' => false, 'message' => 'Código expirado. Solicite novo envio.'], 410);
            }

            if (! hash_equals((string) $payload['code'], (string) $code)) {
                return new WP_REST_Response(['verified' => false, 'message' => 'Código inválido. Digite os 6 dígitos enviados por email.'], 422);
            }

            rma_mark_2fa_verified($user_id);
            delete_transient(rma_otp_transient_key($user_id));

            return new WP_REST_Response(['verified' => true, 'message' => 'Verificação confirmada.']);
        },
    ]);
});

add_action('wp_enqueue_scripts', function () {
    $base = rma_ui_theme_base();
    if (! $base) {
        return;
    }

    $css_file = trailingslashit($base['path']) . 'rma-glass-theme.css';
    $js_file  = trailingslashit($base['path']) . 'rma-glass-theme.js';

    if (file_exists($css_file)) {
        wp_enqueue_style('rma-glass-theme', trailingslashit($base['uri']) . 'rma-glass-theme.css', [], (string) filemtime($css_file));
    }

    if (file_exists($js_file)) {
        wp_enqueue_script('rma-glass-theme-js', trailingslashit($base['uri']) . 'rma-glass-theme.js', [], (string) filemtime($js_file), true);
    }

    wp_enqueue_style('rma-federo-font', 'https://fonts.googleapis.com/css2?family=Federo&display=swap', [], null);
    wp_enqueue_style('rma-mavenpro-font', 'https://fonts.googleapis.com/css2?family=Maven+Pro:wght@400;500;600;700&display=swap', [], null);
});

add_shortcode('rma_glass_card_demo', function () {
    ob_start();
    ?>
    <section class="rma-glass-card" style="margin:20px 0;">
        <span class="rma-badge">RMA • Glasmorphism Ultra White</span>
        <h2 class="rma-glass-title">Card de demonstração</h2>
        <p class="rma-glass-subtitle">Este bloco usa Federo, #7bad39 e #37302c com acabamento translúcido branco.</p>
        <div class="rma-actions">
            <a class="rma-button" href="<?php echo esc_url(rma_account_setup_url()); ?>">Ir para conta</a>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_shortcode('rma_conta_setup', function () {
    if (! is_user_logged_in()) {
        return '<p>Você precisa estar logado para completar o cadastro da entidade.</p>';
    }

    $required_docs = [
        'ficha_inscricao' => ['label' => 'Ficha de inscrição cadastral', 'tip' => 'Ficha preenchida e assinada. Assinatura gov.br é aceita.'],
        'comprovante_cnpj' => ['label' => 'Comprovante de CNPJ', 'tip' => 'Comprovante de Inscrição e Situação Cadastral (Receita Federal).'],
        'ata_fundacao' => ['label' => 'Ata de fundação', 'tip' => 'Ata registrada ou PDF escaneado legível.'],
        'ata_diretoria' => ['label' => 'Ata da diretoria atual', 'tip' => 'Ata da eleição da diretoria atual.'],
        'estatuto' => ['label' => 'Estatuto e alterações', 'tip' => 'Estatuto e alterações consolidadas.'],
        'relatorio_atividades' => ['label' => 'Relatório de atividades', 'tip' => 'Últimos 2 anos de atividades.'],
        'cartas_recomendacao' => ['label' => '2 cartas de recomendação', 'tip' => 'De organizações filiadas à RMA.'],
    ];

    $current_user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($current_user_id);
    $otp_verified = rma_is_2fa_verified($current_user_id);
    $setup_notice = isset($_GET['rma_notice']) ? sanitize_key((string) wp_unslash($_GET['rma_notice'])) : '';
    $rest_base = rest_url('rma/v1');
    $rest_nonce = wp_create_nonce('wp_rest');

    $dashboard_url = home_url('/dashboard/');
    $docs_url = apply_filters('rma_docs_page_url', home_url('/documentos/'));
    $finance_url = apply_filters('rma_finance_page_url', home_url('/financeiro/'));
    $checkout_url = apply_filters('rma_checkout_url', home_url('/checkout/'));
    $annual_product_id = (int) get_option('rma_annual_dues_product_id', 0);
    if ($annual_product_id <= 0) {
        $annual_product_id = (int) get_option('rma_woo_product_id', 0);
    }
    $checkout_payment_url = $annual_product_id > 0
        ? add_query_arg('add-to-cart', $annual_product_id, $checkout_url)
        : $checkout_url;
    $checkout_payment_path = (string) wp_parse_url($checkout_payment_url, PHP_URL_PATH);
    $checkout_payment_query = (string) wp_parse_url($checkout_payment_url, PHP_URL_QUERY);
    $checkout_payment_url_attr = $checkout_payment_path . ($checkout_payment_query !== '' ? ('?' . $checkout_payment_query) : '');
    if ($checkout_payment_url_attr === '') {
        $checkout_payment_url_attr = $checkout_payment_url;
    }
    $layout_tune_css = '<style id="rma-layout-tune">
'
        . '.rma-premium-card,.rma-premium-card--setup{max-width:900px;margin:0 auto;border-radius:18px;background:#fff;border:1px solid #edf1f4;box-shadow:0 16px 40px rgba(0,0,0,.05);padding:28px;}
'
        . '.rma-premium-form{display:grid;gap:16px;}
'
        . '.rma-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
'
        . '.rma-grid-3{display:grid;grid-template-columns:1.2fr .6fr .8fr;gap:14px;}
'
        . '.rma-field-label{display:block;font-size:.88rem;color:#5a6472;margin:0 0 6px;}
'
        . '.rma-auth-card{background:#fff;border:1px solid #edf1f4;border-radius:18px;padding:20px;margin:14px 0 18px;}
'
        . '.rma-auth-title{margin:0 0 6px;font-size:1.35rem;font-weight:700;color:#1f2937;text-align:center;}
'
        . '.rma-auth-subtitle{margin:0 0 14px;color:#4b5563;font-size:.95rem;text-align:center;}
'
        . '.rma-otp-grid{display:grid;grid-template-columns:repeat(6,minmax(0,50px));gap:10px;margin:0 auto 12px;justify-content:center;}
'
        . '.rma-otp-input{height:50px;border:1px solid #d7dee7;border-radius:12px;text-align:center;font-size:1.1rem;font-weight:600;outline:none;transition:border-color .2s ease,box-shadow .2s ease;}
'
        . '.rma-otp-input:focus{border-color:#7bad39;box-shadow:0 0 0 3px rgba(123,173,57,.16);}
'
        . '.rma-otp-input.is-error{border-color:#ef4444;}
'
        . '.rma-otp-input.is-success{border-color:#22c55e;}
'
        . '.rma-resend-link{color:#4b5563;font-size:.86rem;text-decoration:none;display:inline-block;margin-top:8px;}
'
        . '.rma-resend-link[aria-disabled="true"]{pointer-events:none;opacity:.55;}
'
        . '.rma-flow-stepper{position:relative;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:12px 0 14px;--rma-progress:0%;}
'
        . '.rma-flow-stepper::before{content:"";position:absolute;left:8%;right:8%;top:24px;height:4px;border-radius:999px;background:#e9eef3;z-index:0;}
'
        . '.rma-flow-stepper::after{content:"";position:absolute;left:8%;top:24px;height:4px;border-radius:999px;width:var(--rma-progress);background:linear-gradient(135deg,#7bad39,#5ddabb);z-index:1;transition:width .35s ease;}
'
        . '.rma-flow-step{position:relative;z-index:2;background:#fff;border:1px solid #e8edf2;border-radius:12px;padding:10px 14px;box-shadow:0 10px 30px rgba(0,0,0,.06);display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease,filter .25s ease;}
'
        . '.rma-flow-step:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(0,0,0,.1);}
'
        . '.rma-flow-step-figure{width:40px;height:40px;border-radius:12px;background:radial-gradient(circle at 30% 25%,rgba(93,218,187,.35),rgba(123,173,57,.14));display:flex;align-items:center;justify-content:center;box-shadow:inset 0 0 0 1px rgba(123,173,57,.2);}
'
        . '.rma-flow-step-figure svg,.rma-flow-step-figure img{width:40px;height:40px;display:block;}\n'
        . '.rma-flow-step-label{font-size:13px;font-weight:500;color:#475569;line-height:1.1;}
'
        . '.rma-flow-step-badge{position:absolute;top:6px;right:6px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;background:#e7edf4;color:#90a0b3;}
'
        . '.rma-flow-step.is-done{border-color:#bfe3b0;}
'
        . '.rma-flow-step.is-done .rma-flow-step-label{color:#2f7d32;}
'
        . '.rma-flow-step.is-done .rma-flow-step-badge{background:#2f7d32;color:#fff;}
'
        . '.rma-flow-step.is-current{border-color:transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#7bad39,#5ddabb) border-box;transform:translateY(-2px);}
'
        . '.rma-flow-step.is-current .rma-flow-step-figure{transform:scale(1.06);box-shadow:0 8px 20px rgba(93,218,187,.32), inset 0 0 0 1px rgba(93,218,187,.4);animation:rma-step-float 1.8s ease-in-out infinite;}
'
        . '.rma-flow-step.is-current .rma-flow-step-label{color:#0f172a;}
'
        . '.rma-flow-step.is-current .rma-flow-step-badge{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;}
'
        . '.rma-flow-step.is-locked{background:#f8fafc;border-color:#e5e9ef;filter:saturate(.3);}
'
        . '.rma-flow-step.is-locked .rma-flow-step-label{color:#94a3b8;}
'
        . '.rma-flow-step.is-locked .rma-flow-step-badge{background:#eef2f7;color:#a9b4c2;}
'
        . '@keyframes rma-step-float{0%,100%{transform:translateY(0) scale(1.06);}50%{transform:translateY(-2px) scale(1.06);}}
'
        . '.rma-actions{display:flex;justify-content:space-between;align-items:center;margin-top:18px;gap:10px;width:100%;}\n'
        . '.rma-primary-cta,.rma-nav-actions{display:flex;gap:10px;align-items:center;}\n'
        . '.rma-primary-cta{margin-left:auto;}\n'
        . '.rma-primary-cta .btn-rma-primary{margin-left:auto;}\n'
        . '.rma-auth-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:8px 0 2px;}\n'
        . '.rma-auth-actions .btn-rma-primary{margin-left:auto;}\n'
        . '.rma-auth-card{text-align:center;}\n'
        . '.rma-glass-title{color:#1f2937;font-weight:700;}\n'
        . '.rma-glass-subtitle{color:#4b5563;}\n'
        . '.btn-rma-primary{background:linear-gradient(135deg,#7bad39,#5ddabb)!important;border:none!important;color:#fff!important;font-weight:600;padding:12px 24px;border-radius:14px;transition:all .3s ease;box-shadow:0 8px 18px rgba(0,0,0,.08);}
'
        . '.btn-rma-primary:hover{transform:translateY(-2px);filter:brightness(1.04);box-shadow:0 12px 24px rgba(0,0,0,.12);}
'
        . '.btn-rma-secondary{background:#fff!important;border:1px solid #d7dee7!important;color:#4b5563!important;font-weight:600;padding:12px 20px;border-radius:14px;transition:all .3s ease;}
'
        . '.btn-rma-secondary:hover{transform:translateY(-2px);box-shadow:0 8px 16px rgba(0,0,0,.06);}
'
        . '.rma-modern-dropzone{border:1px dashed #cfd8e3;border-radius:14px;padding:12px;background:#fbfcfd;}
'
        . '.rma-alert--analysis{padding:14px 16px;border-radius:12px;border:1px solid rgba(14,116,144,.22);background:linear-gradient(135deg,rgba(14,116,144,.08),rgba(34,197,94,.08));color:#0f172a;font-weight:600;box-shadow:0 10px 30px rgba(15,23,42,.08);}\n'
        . '.rma-alert--analysis strong{display:block;font-size:1rem;margin-bottom:4px;color:#0b3b66;}\n'
        . '.rma-phone-row{display:grid;grid-template-columns:120px 1fr;gap:10px;}\n'
        . '.rma-drop-item{position:relative;}\n'
        . '.rma-dropzone{border:1px dashed #cfd8e3;border-radius:12px;padding:12px;background:#fbfcfd;display:flex;flex-direction:column;gap:8px;}\n'
        . '.rma-dropzone.is-drag{border-color:#7bad39;background:#f4fbef;}\n'
        . '.rma-file-preview{font-size:.82rem;color:#4b5563;word-break:break-word;}\n'
        . '@media(max-width:860px){.rma-grid-2,.rma-grid-3{grid-template-columns:1fr;}.rma-flow-stepper{display:flex;overflow-x:auto;padding-bottom:6px;scroll-snap-type:x mandatory;}.rma-flow-step{min-width:132px;scroll-snap-align:start;}.rma-flow-stepper::before,.rma-flow-stepper::after{display:none;}.rma-otp-grid{grid-template-columns:repeat(6,minmax(0,1fr));}}\n'
        . '@media(max-width:600px){.rma-actions{flex-direction:column;gap:10px;align-items:stretch;}.rma-primary-cta,.rma-nav-actions{width:100%;}.rma-primary-cta .btn-rma-primary,.rma-nav-actions .btn-rma-secondary{width:100%;}.rma-primary-hint,#rma-primary-hint{text-align:left!important;}}\n'
        . '</style>';

    if ($entity_id > 0) {
        $governance = (string) get_post_meta($entity_id, 'governance_status', true);
        $finance = (string) get_post_meta($entity_id, 'finance_status', true);
        $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);
        $all_steps_done = ($governance === 'aprovado' && $finance === 'adimplente' && in_array($docs_status, ['enviado', 'aprovado', 'validado', 'aceito'], true));

        $docs_reupload_statuses = ['rejeitado', 'negado', 'pendente_reenvio', 'correcao', 'reprovado'];
        $uploaded_document_types = rma_get_entity_uploaded_document_types($entity_id);
        $required_document_types = array_keys($required_docs);
        $missing_document_types = array_values(array_diff($required_document_types, $uploaded_document_types));
        $docs_collection_complete = empty($missing_document_types);
        $show_docs_upload = (! $docs_collection_complete) || in_array($docs_status, $docs_reupload_statuses, true);

        $rejected_document_types = get_post_meta($entity_id, 'documentos_reprovados', true);
        $rejected_document_types = is_array($rejected_document_types)
            ? array_values(array_filter(array_map('sanitize_key', $rejected_document_types)))
            : [];

        $docs_to_render = $required_docs;
        $docs_approved_statuses = ['aprovado', 'validado', 'aceito'];
        $docs_approved = in_array($docs_status, $docs_approved_statuses, true) || $governance === 'aprovado';
        $latest_dues_order = rma_theme_get_latest_dues_order_snapshot($entity_id);
        $latest_dues_status = (string) ($latest_dues_order['status'] ?? '');
        $show_payment_waiting_card = $docs_approved
            && $finance !== 'adimplente'
            && in_array($latest_dues_status, ['pending', 'on-hold', 'processing'], true);
        $show_activation_welcome_card = ($governance === 'aprovado' && $finance === 'adimplente');
        if ($show_docs_upload && ! empty($rejected_document_types)) {
            $docs_to_render = [];
            foreach ($rejected_document_types as $doc_key) {
                if (isset($required_docs[$doc_key])) {
                    $docs_to_render[$doc_key] = $required_docs[$doc_key];
                }
            }
            if (empty($docs_to_render) && ! empty($missing_document_types)) {
                foreach ($missing_document_types as $doc_key) {
                    if (isset($required_docs[$doc_key])) {
                        $docs_to_render[$doc_key] = $required_docs[$doc_key];
                    }
                }
            }
            if (empty($docs_to_render)) {
                $docs_to_render = $required_docs;
            }
        } elseif ($show_docs_upload && ! empty($missing_document_types)) {
            $docs_to_render = [];
            foreach ($missing_document_types as $doc_key) {
                if (isset($required_docs[$doc_key])) {
                    $docs_to_render[$doc_key] = $required_docs[$doc_key];
                }
            }
            if (empty($docs_to_render)) {
                $docs_to_render = $required_docs;
            }
        }

        ob_start();
        ?>
        <?php echo $layout_tune_css; ?>
        <section class="rma-glass-card rma-premium-card" style="margin:20px 0;">
            <h2 class="rma-glass-title">Central de Ativação RMA</h2>
            <p class="rma-glass-subtitle">Finalize as etapas pendentes para liberar seu ambiente exclusivo na plataforma RMA.</p>

            <?php if ($setup_notice === 'entity-created') : ?>
                <div class="rma-alert" style="margin:0 0 16px;">
                    Entidade criada e documentos enviados. Iniciando validação de segurança...
                </div>
            <?php endif; ?>

            <section class="rma-auth-card" id="rma-auth-card">
                <h3 class="rma-auth-title">Confirmação de Identidade</h3>
                <p class="rma-auth-subtitle">Para sua segurança, valide o código enviado ao seu email institucional.</p>
                <div class="rma-otp-grid" id="rma-otp-grid">
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="0" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="1" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="2" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="3" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="4" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="5" />
                </div>
                <div class="rma-auth-actions">
                    <button class="rma-button btn-rma-secondary" type="button" id="btnVoltar">Voltar</button>
                    <button class="rma-button btn-rma-primary" type="button" id="rma-validate-code">Validar Código</button>
                </div>
                <a href="#" class="rma-resend-link" id="rma-resend-code" aria-disabled="true">Reenviar código em <span id="rma-resend-timer">60</span>s</a>
            </section>

            <div id="rma-onboarding-main" style="display:none;">
            <div class="rma-flow-stepper" id="rma-flow-stepper">
                <div class="rma-flow-step is-done" data-step="1">
                    <span class="rma-flow-step-badge">✓</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('verificacao.gif')); ?>" alt="Verificação" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Verificação</div>
                </div>
                <div class="rma-flow-step is-done" data-step="2">
                    <span class="rma-flow-step-badge">✓</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('cadastro.gif')); ?>" alt="Cadastro" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Cadastro</div>
                </div>
                <div class="rma-flow-step is-current" data-step="3">
                    <span class="rma-flow-step-badge">•</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('documentos.gif')); ?>" alt="Documentos" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Documentos</div>
                </div>
                <div class="rma-flow-step is-locked" data-step="4">
                    <span class="rma-flow-step-badge">○</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('validacao.gif')); ?>" alt="Validação" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Validação</div>
                </div>
                <div class="rma-flow-step is-locked" data-step="5">
                    <span class="rma-flow-step-badge">○</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('pagamento.gif')); ?>" alt="Pagamento" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Pagamento</div>
                </div>
            </div>

            <ul style="margin:12px 0 16px 18px;">
                <li><strong>Governança:</strong> <span id="rma-status-governanca"><?php echo esc_html($governance ?: 'pendente'); ?></span></li>
                <li><strong>Documentos:</strong> <span id="rma-status-documentos"><?php echo esc_html($docs_status ?: 'pendente'); ?></span></li>
                <li><strong>Financeiro:</strong> <span id="rma-status-financeiro"><?php echo esc_html($finance ?: 'pendente'); ?></span></li>
            </ul>

            <div style="margin:0 0 14px;">
                <?php if ($show_payment_waiting_card) : ?>
                    <div class="rma-alert rma-alert--analysis" id="rma-flow-message-panel" style="margin-top:8px;">
                        <strong>Pagamento em acompanhamento pela RMA.</strong>
                        Sua documentação já foi validada e sua solicitação de filiação está em etapa final. Assim que a compensação do pagamento for reconhecida, sua filiação será concluída automaticamente e o acesso completo será liberado com toda a segurança.
                    </div>
                <?php elseif ($show_activation_welcome_card) : ?>
                    <div class="rma-alert rma-alert--analysis" id="rma-flow-message-panel" style="margin-top:8px;">
                        <strong>Seja muito bem-vinda à RMA.</strong>
                        Sua filiação foi confirmada e seu ambiente já está pronto para receber sua atuação na rede. A partir daqui, sua central passa a ser o espaço oficial para acompanhar oportunidades, avisos e próximos movimentos da sua entidade dentro da comunidade RMA.
                    </div>
                <?php else : ?>
                <h3 class="rma-premium-section-title"><?php echo $docs_approved ? 'Etapa de pagamento' : 'Etapa de documentos'; ?></h3>

                <?php if ($show_docs_upload) : ?>
                <?php if (! empty($rejected_document_types)) : ?>
                    <div class="rma-alert" style="margin:8px 0 10px;">
                        A Equipe RMA identificou ajustes necessários. Apenas os itens abaixo precisam ser reenviados para continuidade da validação.
                    </div>
                <?php endif; ?>

                    <ul class="rma-docs-list rma-modern-dropzone" id="rma-doc-upload-block">
                        <?php foreach ($docs_to_render as $doc_key => $doc_meta) : ?>
                            <li>
                                <label style="display:flex;align-items:center;gap:8px;">
                                    <span><?php echo esc_html($doc_meta['label']); ?></span>
                                    <span title="<?php echo esc_attr($doc_meta['tip']); ?>" style="cursor:help;">ⓘ</span>
                                </label>
                                <div class="rma-drop-item"><label class="rma-dropzone" for="rma-doc-file-<?php echo esc_attr($doc_key); ?>"><span>Arraste e solte ou selecione arquivo</span><input type="file" id="rma-doc-file-<?php echo esc_attr($doc_key); ?>" data-doc-key="<?php echo esc_attr($doc_key); ?>" class="rma-doc-file" accept="application/pdf,image/*,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" /><small class="rma-file-preview" id="rma-preview-<?php echo esc_attr($doc_key); ?>">Nenhum arquivo selecionado</small></label></div>
                                <button class="rma-button rma-doc-upload" type="button" data-doc-key="<?php echo esc_attr($doc_key); ?>">Enviar arquivo</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="rma-alert rma-alert--analysis" id="rma-doc-upload-block" style="margin-top:8px;">
                        <?php if ($docs_approved) : ?>
                            <strong>Documentação aprovada e pagamento liberado.</strong>
                            Sua filiação está a um passo da conclusão. Clique em <em>Avançar para Pagamento</em> para quitar a anuidade e liberar o acesso completo da sua entidade ao ambiente RMA.
                        <?php else : ?>
                            <strong>Documentação recebida com sucesso.</strong>
                            Seus documentos estão em análise pela Equipe RMA. Assim que a validação for concluída, você será notificado por e-mail e também por esta central.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="rma-actions">
                <div class="rma-nav-actions">
                    <button class="rma-button btn-rma-secondary" type="button" id="rma-back-action">Voltar</button>
                </div>
                <div class="rma-primary-cta">
                    <button class="rma-button btn-rma-primary" type="button" id="btnPagamento" data-checkout-url="<?php echo esc_url($checkout_payment_url_attr); ?>" data-product-id="<?php echo (int) $annual_product_id; ?>" data-rma-pay="1">Avançar para Pagamento</button>
                </div>
            </div>
            <span id="rma-primary-hint" style="display:block;width:100%;text-align:right;font-size:.92rem;color:#6b6b6b;margin-top:6px;"></span>

            <div id="rma-flow-feedback" class="rma-feedback"></div>
            </div>
        </section>

        <script>
        (function () {
            var entityId = <?php echo (int) $entity_id; ?>;
            var base = <?php echo wp_json_encode($rest_base); ?>;
            var nonce = <?php echo wp_json_encode($rest_nonce); ?>;
            var feedback = document.getElementById('rma-flow-feedback');
            var primaryAction = document.getElementById('btnPagamento') || document.getElementById('rma-primary-action');
            var primaryHint = document.getElementById('rma-primary-hint');
            var checkoutUrl = <?php echo wp_json_encode($checkout_url); ?>;
            var checkoutPaymentUrl = <?php echo wp_json_encode($checkout_payment_url); ?>;
            var annualProductId = <?php echo (int) $annual_product_id; ?>;
            var dashboardUrl = <?php echo wp_json_encode($dashboard_url); ?>;
            var stepper = document.getElementById('rma-flow-stepper');
            var backAction = document.getElementById('rma-back-action');
            var authBackButton = document.getElementById('btnVoltar');
            var onboardingMain = document.getElementById('rma-onboarding-main');
	            var currentUiStep = 1;
	            var isOtpVerified = <?php echo $otp_verified ? 'true' : 'false'; ?>;
	            var paymentUnlocked = false;
                var requiredDocKeys = <?php echo wp_json_encode(array_keys($required_docs)); ?>;
                var uploadedDocTypes = <?php echo wp_json_encode(array_values($uploaded_document_types)); ?>;
                var latestDuesStatus = <?php echo wp_json_encode($latest_dues_status); ?>;
                var resumeUrl = new URL(window.location.href);

                function missingRequiredDocKeys() {
                    return requiredDocKeys.filter(function (docKey) {
                        return uploadedDocTypes.indexOf(docKey) === -1;
                    });
                }

                function updateResumeStep(stepName) {
                    try {
                        resumeUrl.searchParams.set('rma_step', stepName);
                        window.history.replaceState({}, '', resumeUrl.toString());
                    } catch (e) {}
                }

	            function revealMainFlow() {
                var card = document.getElementById('rma-auth-card');
                if (card) card.style.display = 'none';
                if (onboardingMain) onboardingMain.style.display = 'block';
            }

            function sendOtpCode(initial) {
                return fetch(base + '/otp/send', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } })
                    .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            showFeedback((result.json && result.json.message) ? result.json.message : 'Não foi possível enviar o código no momento. Tente novamente ou verifique sua caixa de spam.', false);
                            return { sent: false, message: '' };
                        }
                        var successMessage = (result.json && result.json.message) ? result.json.message : 'Código enviado para seu email institucional.';
                        if (!initial) showFeedback(successMessage, true);
                        return { sent: true, message: successMessage };
                    })
                    .catch(function () {
                        showFeedback('Não foi possível enviar o código no momento. Tente novamente ou verifique sua caixa de spam.', false);
                        return { sent: false, message: '' };
                    });
            }

            function resolveCheckoutPaymentUrl() {
                var productId = annualProductId > 0 ? annualProductId : 0;
                if (productId <= 0) {
                    return checkoutUrl;
                }
                return checkoutUrl + (checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 'add-to-cart=' + productId;
            }

            function redirectToCheckout() {
                var paymentUrl = resolveCheckoutPaymentUrl();

                if (!primaryAction) {
                    window.location.href = paymentUrl;
                    return;
                }

                primaryAction.disabled = true;
                primaryAction.textContent = 'Processando pagamento...';
                showFeedback('Processando pagamento... redirecionando para checkout.', true);
                window.location.href = paymentUrl;
            }

            if (authBackButton) {
                authBackButton.addEventListener('click', function () {
                    window.history.back();
                });
            }

            function showFeedback(message, ok) {
                if (!feedback) return;
                feedback.innerHTML = '<div style="padding:10px;border-radius:10px;background:' + (ok ? '#edf9ec' : '#fdecec') + ';">' + message + '</div>';
            }


            function initDropzones(selector) {
                document.querySelectorAll(selector).forEach(function (input) {
                    var zone = input.closest('.rma-dropzone');
                    if (!zone || zone.getAttribute('data-drop-init') === '1') return;
                    zone.setAttribute('data-drop-init', '1');
                    var preview = zone.querySelector('.rma-file-preview');
                    function update() {
                        var file = input.files && input.files[0] ? input.files[0] : null;
                        if (preview) preview.textContent = file ? (file.name + ' • ' + Math.ceil(file.size / 1024) + ' KB') : 'Nenhum arquivo selecionado';
                    }
                    input.addEventListener('change', update);
                    zone.addEventListener('dragover', function (event) { event.preventDefault(); zone.classList.add('is-drag'); });
                    zone.addEventListener('dragleave', function () { zone.classList.remove('is-drag'); });
                    zone.addEventListener('drop', function (event) {
                        event.preventDefault();
                        zone.classList.remove('is-drag');
                        if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
                            input.files = event.dataTransfer.files;
                            update();
                        }
                    });
                });
            }

            function applyStepper(currentStep, doneOverrides) {
                if (!stepper) return;
                currentUiStep = currentStep;
                var stepMap = {1: 'identity', 2: 'cadastro', 3: 'documentos', 4: 'validacao', 5: paymentUnlocked ? 'pagamento' : 'dashboard'};
                updateResumeStep(stepMap[currentStep] || 'identity');
                doneOverrides = Array.isArray(doneOverrides) ? doneOverrides : [];
                var progress = ((Math.max(1, Math.min(5, currentStep)) - 1) / 4) * 84;
                stepper.style.setProperty('--rma-progress', progress + '%');

                stepper.querySelectorAll('.rma-flow-step').forEach(function (item) {
                    var n = parseInt(item.getAttribute('data-step') || '0', 10);
                    var badge = item.querySelector('.rma-flow-step-badge');
                    item.classList.remove('is-done', 'is-current', 'is-locked');
                    if (n < currentStep || doneOverrides.indexOf(n) !== -1) {
                        item.classList.add('is-done');
                        if (badge) badge.textContent = '✓';
                    } else if (n === currentStep) {
                        item.classList.add('is-current');
                        if (badge) badge.textContent = '•';
                    } else {
                        item.classList.add('is-locked');
                        if (badge) badge.textContent = '○';
                    }
                });

                if (backAction) {
                    backAction.disabled = currentStep <= 1;
                }
            }

            function formatStatusLabel(value, context) {
                var raw = String(value || 'pendente');
                var map = {
                    governance: {
                        pendente: 'Pendente',
                        em_analise: 'Em Análise',
                        aprovado: 'Liberado',
                        recusado: 'Recusado',
                        suspenso: 'Suspenso'
                    },
                    documentos: {
                        pendente: 'Pendente',
                        pendente_reenvio: 'Pendente de Reenvio',
                        enviado: 'Enviado',
                        aprovado: 'Aprovado',
                        validado: 'Validado',
                        aceito: 'Aceito'
                    },
                    financeiro: {
                        pendente: 'Pendente',
                        inadimplente: 'Inadimplente',
                        adimplente: 'Adimplente'
                    }
                };

                if (context && map[context] && map[context][raw]) {
                    return map[context][raw];
                }

                return raw.replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
            }

            function applyPrimaryAction(payload) {
                if (!primaryAction) return;
                if (!isOtpVerified) {
                    primaryAction.disabled = true;
                    primaryAction.textContent = 'Valide o código para continuar';
                    primaryAction.removeAttribute('data-rma-pay');
                    if (primaryHint) primaryHint.textContent = 'Confirme o código enviado para seu email para liberar as próximas etapas.';
                    return;
                }
	                var docs = payload.documentos_status || 'pendente';
	                var finance = payload.finance_status || 'pendente';
	                var governance = payload.governance_status || 'pendente';
	                var rejected = Array.isArray(payload.rejected_document_types) ? payload.rejected_document_types : [];
                    var missingDocs = missingRequiredDocKeys();
	                var docsApproved = ['aprovado', 'validado', 'aceito'].indexOf(docs) !== -1 || governance === 'aprovado';
	                var docsWaiting = docs === 'enviado' && rejected.length === 0 && missingDocs.length === 0 && !docsApproved;
	                var docsNeedUpload = missingDocs.length > 0 || rejected.length > 0 || ((docs !== 'enviado') && !docsApproved);

	                paymentUnlocked = false;
	                primaryAction.disabled = false;
                    primaryAction.style.display = '';
	                primaryAction.textContent = 'Continuar';
                primaryAction.removeAttribute('data-rma-pay');
                primaryAction.onclick = null;
                if (primaryHint) primaryHint.textContent = '';

                if (governance === 'aprovado' && finance === 'adimplente') {
                    applyStepper(5, [5]);
                    updateResumeStep('dashboard');
                    primaryAction.textContent = 'Seguir para Central da Entidade';
                    primaryAction.disabled = false;
                    primaryAction.removeAttribute('data-rma-pay');
                    primaryAction.removeAttribute('data-checkout-url');
                    primaryAction.onclick = function () { window.location.assign(dashboardUrl); };
                    if (primaryHint) primaryHint.textContent = 'Tudo concluído. Acesse sua central para acompanhar os próximos passos.';
                    return;
                }

                if (governance === 'suspenso') {
                    applyStepper(4);
                    primaryAction.textContent = 'Acesso Suspenso';
                    primaryAction.disabled = true;
                    primaryAction.removeAttribute('data-rma-pay');
                    primaryAction.removeAttribute('data-checkout-url');
                    if (primaryHint) primaryHint.textContent = 'Sua entidade foi suspensa pela equipe administrativa. Entre em contato com a RMA.';
                    return;
                }

	                if (docsNeedUpload) {
	                    applyStepper(3);
	                    primaryAction.textContent = missingDocs.length > 0 ? 'Enviar Documentos Restantes' : 'Reenviar Documentos';
	                    primaryAction.onclick = function () {
	                        var el = document.getElementById('rma-doc-upload-block');
	                        if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
	                    };
	                    if (primaryHint) {
                            primaryHint.textContent = missingDocs.length > 0
                                ? 'Ainda faltam documentos obrigatórios para concluir a etapa documental. Envie os itens restantes para seguir no fluxo.'
                                : 'Identificamos ajustes necessários na documentação enviada. Revise as orientações e realize o reenvio para continuidade do processo.';
                        }
	                    return;
	                }

                if (docsWaiting && finance === 'adimplente') {
                    applyStepper(4, [5]);
                    primaryAction.textContent = 'Em Análise';
                    primaryAction.disabled = true;
                    if (primaryHint) primaryHint.textContent = 'Pagamento confirmado. Sua entidade permanece em validação até o parecer final da equipe.';
                    return;
                }

                if (docsWaiting) {
                    applyStepper(4);
                    primaryAction.textContent = 'Documentos em análise pela Equipe RMA';
                    primaryAction.disabled = true;
                    primaryAction.removeAttribute('data-rma-pay');
                    primaryAction.removeAttribute('data-checkout-url');
                    if (primaryHint) primaryHint.textContent = '';
                    return;
                }

                    if (docsApproved && finance !== 'adimplente' && ['pending', 'on-hold', 'processing'].indexOf(latestDuesStatus) !== -1) {
                        applyStepper(5, [4]);
                        primaryAction.style.display = 'none';
                        primaryAction.disabled = true;
                        primaryAction.removeAttribute('data-rma-pay');
                        primaryAction.removeAttribute('data-checkout-url');
                        if (primaryHint) {
                            primaryHint.textContent = 'Estamos aguardando a confirmação do pagamento para concluir sua filiação com toda a segurança. Assim que a compensação for reconhecida, seu acesso será atualizado automaticamente.';
                        }
                        return;
                    }

	                if (docsApproved && finance !== 'adimplente') {
	                    applyStepper(5, [4]);
	                    paymentUnlocked = true;
                    updateResumeStep('pagamento');
                    primaryAction.textContent = annualProductId > 0 ? 'Avançar para Pagamento' : 'Ir para Checkout';
                    primaryAction.id = 'btnPagamento';
                    primaryAction.setAttribute('data-rma-pay', '1');
                    primaryAction.setAttribute('data-checkout-url', checkoutPaymentUrl);
                    primaryAction.onclick = redirectToCheckout;
                    if (primaryHint) primaryHint.textContent = 'Próxima etapa: concluir pagamento no checkout.';
                    return;
                }

                if (docsApproved && finance === 'adimplente' && governance !== 'aprovado') {
                    applyStepper(4, [5]);
                    primaryAction.textContent = 'Em Análise';
                    primaryAction.disabled = true;
                    primaryAction.removeAttribute('data-rma-pay');
                    if (primaryHint) primaryHint.textContent = 'Pagamento confirmado. Sua entidade permanece em validação até o parecer final da equipe.';
                    return;
                }

                applyStepper(1);
                primaryAction.textContent = 'Aguardando Status';
                primaryAction.disabled = true;
                if (primaryHint) primaryHint.textContent = 'Acompanhe a etapa de status/governança.';
            }

            function updateState(payload) {
                var g = document.getElementById('rma-status-governanca');
                var d = document.getElementById('rma-status-documentos');
                var f = document.getElementById('rma-status-financeiro');
                if (g) g.textContent = formatStatusLabel(payload.governance_status || 'pendente', 'governance');
                if (d) d.textContent = formatStatusLabel(payload.documentos_status || 'pendente', 'documentos');
                if (f) f.textContent = formatStatusLabel(payload.finance_status || 'pendente', 'financeiro');
                applyPrimaryAction(payload || {});
            }

            function refreshStatus() {
                fetch(base + '/entities/' + entityId + '/status', {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                .then(function (result) {
                    if (!result.ok) {
                        showFeedback((result.json && result.json.message) ? result.json.message : 'Falha ao atualizar status automaticamente.', false);
                        return;
                    }
                    updateState(result.json || {});
                })
                .catch(function () {
                    showFeedback('Erro de conexão ao atualizar status automaticamente.', false);
                });
            }

            function uploadDoc(docKey, file) {
                if (!file) {
                    showFeedback('Selecione um arquivo antes de enviar.', false);
                    return;
                }

                var data = new FormData();
                data.append('document_type', docKey);
                data.append('file', file);

                showFeedback('Enviando ' + docKey + '...', true);
                fetch(base + '/entities/' + entityId + '/documents', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce },
                    body: data
                })
                .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                .then(function (result) {
	                    if (!result.ok) {
	                        showFeedback((result.json && result.json.message) ? result.json.message : 'Falha no upload do documento.', false);
	                        return;
	                    }
                        if (uploadedDocTypes.indexOf(docKey) === -1) {
                            uploadedDocTypes.push(docKey);
                        }
	                    showFeedback('Documento enviado com sucesso (' + docKey + ').', true);
	                    refreshStatus();
	                })
                .catch(function () {
                    showFeedback('Erro de conexão no upload.', false);
                });
            }

            initDropzones('.rma-doc-file');

            if (backAction) {
                backAction.addEventListener('click', function () {
                    if (currentUiStep <= 1) {
                        return;
                    }

                    if (currentUiStep <= 2) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }

                    if (document.referrer) {
                        window.history.back();
                        return;
                    }

                    var uploadBlock = document.getElementById('rma-doc-upload-block');
                    if (uploadBlock) uploadBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }


            var otpInputs = Array.prototype.slice.call(document.querySelectorAll('.rma-otp-input'));
            var validateCodeButton = document.getElementById('rma-validate-code');
            var resendCodeLink = document.getElementById('rma-resend-code');
            var resendTimerElement = document.getElementById('rma-resend-timer');
            var resendSeconds = 60;

            function setOtpState(state) {
                otpInputs.forEach(function (input) {
                    input.classList.remove('is-error', 'is-success');
                    if (state) input.classList.add(state);
                });
            }

            function otpCode() {
                return otpInputs.map(function (input) { return (input.value || '').trim(); }).join('');
            }

            otpInputs.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    input.value = (input.value || '').replace(/\D+/g, '').slice(0, 1);
                    if (input.value && otpInputs[index + 1]) otpInputs[index + 1].focus();
                });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !input.value && otpInputs[index - 1]) {
                        otpInputs[index - 1].focus();
                    }
                });
                input.addEventListener('paste', function (event) {
                    var text = (event.clipboardData || window.clipboardData).getData('text') || '';
                    var digits = text.replace(/\D+/g, '').slice(0, 6).split('');
                    if (!digits.length) return;
                    event.preventDefault();
                    otpInputs.forEach(function (el, i) { el.value = digits[i] || ''; });
                    var next = otpInputs[Math.min(digits.length, 5)];
                    if (next) next.focus();
                });
            });

            if (validateCodeButton) {
                validateCodeButton.addEventListener('click', function () {
                    if (otpCode().length !== 6) {
                        setOtpState('is-error');
                        showFeedback('Código inválido. Digite os 6 dígitos enviados por email.', false);
                        return;
                    }

                    fetch(base + '/otp/verify', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                        body: JSON.stringify({ code: otpCode() })
                    })
                    .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                    .then(function (result) {
                        if (!result.ok || !result.json || !result.json.verified) {
                            setOtpState('is-error');
                            showFeedback((result.json && result.json.message) ? result.json.message : 'Código inválido. Digite os 6 dígitos enviados por email.', false);
                            return;
                        }

                        isOtpVerified = true;
                        setOtpState('is-success');
                    var card = document.getElementById('rma-auth-card');
                    if (card) card.style.display = 'none';
                    if (onboardingMain) onboardingMain.style.display = 'block';
                        showFeedback('Documentos enviados.', true);
                        refreshStatus();
                    })
                    .catch(function () {
                        setOtpState('is-error');
                        showFeedback('Não foi possível validar o código no momento. Tente novamente.', false);
                    });
                });
            }

            var resendTimerInterval = setInterval(function () {
                resendSeconds = Math.max(0, resendSeconds - 1);
                if (resendTimerElement) resendTimerElement.textContent = String(resendSeconds);
                if (resendSeconds === 0 && resendCodeLink) {
                    resendCodeLink.setAttribute('aria-disabled', 'false');
                    resendCodeLink.textContent = 'Reenviar código';
                    clearInterval(resendTimerInterval);
                }
            }, 1000);

            if (resendCodeLink) {
                resendCodeLink.addEventListener('click', function (event) {
                    if (resendCodeLink.getAttribute('aria-disabled') === 'true') {
                        event.preventDefault();
                        return;
                    }
                    event.preventDefault();
                    resendSeconds = 60;
                    resendCodeLink.setAttribute('aria-disabled', 'true');
                    resendCodeLink.innerHTML = 'Reenviar código em <span id="rma-resend-timer">60</span>s';
                    resendTimerElement = document.getElementById('rma-resend-timer');
                    sendOtpCode(false);
                    resendTimerInterval = setInterval(function () {
                        resendSeconds = Math.max(0, resendSeconds - 1);
                        if (resendTimerElement) resendTimerElement.textContent = String(resendSeconds);
                        if (resendSeconds === 0) {
                            resendCodeLink.setAttribute('aria-disabled', 'false');
                            resendCodeLink.textContent = 'Reenviar código';
                            clearInterval(resendTimerInterval);
                        }
                    }, 1000);
                });
            }


            if (onboardingMain) onboardingMain.style.display = 'none';

            if (isOtpVerified) {
                updateResumeStep('documentos');
                revealMainFlow();
            }

            fetch(base + '/otp/status', { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } })
                .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                .then(function (result) {
                    if (result.ok && result.json && result.json.verified) {
                        isOtpVerified = true;
                        revealMainFlow();
                        return;
                    }
                    if (isOtpVerified) {
                        return;
                    }
                    sendOtpCode(true).then(function (result) {
                        if (result && result.sent) showFeedback(result.message || 'Código enviado para seu email institucional.', true);
                    });
                })
                .catch(function () {
                    if (isOtpVerified) {
                        return;
                    }
                    sendOtpCode(true).then(function (result) {
                        if (result && result.sent) showFeedback(result.message || 'Código enviado para seu email institucional.', true);
                    });
                });

            updateState({
                governance_status: <?php echo wp_json_encode($governance); ?>,
                documentos_status: <?php echo wp_json_encode($docs_status); ?>,
                finance_status: <?php echo wp_json_encode($finance); ?>,
                rejected_document_types: <?php echo wp_json_encode($rejected_document_types); ?>
            });

            document.querySelectorAll('.rma-doc-upload').forEach(function (button) {
                button.addEventListener('click', function () {
                    var key = button.getAttribute('data-doc-key');
                    var fileInput = document.querySelector('.rma-doc-file[data-doc-key="' + key + '"]');
                    uploadDoc(key, fileInput && fileInput.files ? fileInput.files[0] : null);
                });
            });

            function pollStatusSilently() {
                fetch(base + '/entities/' + entityId + '/status', {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
                .then(function (data) { updateState(data || {}); })
                .catch(function () {
                    // polling silencioso para não bloquear o CTA de pagamento
                });
            }

            setInterval(pollStatusSilently, 10000);
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    ob_start();
    ?>
    <?php echo $layout_tune_css; ?>
    <section class="rma-glass-card rma-premium-card rma-premium-card--setup" style="margin:20px 0;">
        <span class="rma-badge">RMA • Conta da Entidade</span>
        <h2 class="rma-glass-title">Complete seu cadastro institucional</h2>
        <p class="rma-glass-subtitle">Valide o CNPJ, confirme dados e envie para análise.</p>

        <form id="rma-conta-setup-form" class="rma-premium-form">
            <div class="rma-cnpj-row">
                <input type="text" id="rma-cnpj" placeholder="CNPJ" required />
                <button class="rma-button rma-button--ghost" type="button" id="rma-buscar-cnpj">Buscar CNPJ</button>
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-razao-social" placeholder="Razão social" required />
                <input type="text" id="rma-nome-fantasia" placeholder="Nome fantasia" />
            </div>

            <div class="rma-grid-2">
                <input type="email" id="rma-email" placeholder="E-mail de contato" required />
                <input type="text" id="rma-representante" placeholder="Nome do representante legal" />
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-endereco" placeholder="Endereço" />
                <input type="text" id="rma-bairro" placeholder="Bairro" />
            </div>

            <div class="rma-grid-3">
                <input type="text" id="rma-cidade" placeholder="Cidade" />
                <input type="text" id="rma-uf" placeholder="UF" maxlength="2" />
                <input type="text" id="rma-cep" placeholder="CEP" />
            </div>

            <div class="rma-grid-2">
                <div class="rma-phone-row"><select id="rma-phone-country"><option value="55">🇧🇷 +55</option><option value="1">🇺🇸 +1</option><option value="351">🇵🇹 +351</option><option value="34">🇪🇸 +34</option></select><input type="tel" id="rma-telefone" placeholder="(11) 99999-9999" /></div>
                <div>
                    <label for="rma-data-fundacao" class="rma-field-label">Data de Criação da Entidade</label>
                    <input type="date" id="rma-data-fundacao" />
                </div>
            </div>

            <textarea id="rma-atividades" placeholder="Resumo de atividades (últimos 2 anos)" rows="4"></textarea>

            <div class="rma-docs-block">
                <p class="rma-premium-section-title"><strong>Documentos obrigatórios (PDF, imagem ou Word)</strong></p>
                <ul class="rma-docs-list">
                    <?php foreach ($required_docs as $doc_key => $doc_meta) : ?>
                        <li>
                            <label style="display:flex;align-items:center;gap:8px;">
                                <span><?php echo esc_html($doc_meta['label']); ?></span>
                                <span title="<?php echo esc_attr($doc_meta['tip']); ?>" style="cursor:help;">ⓘ</span>
                            </label>
                            <div class="rma-drop-item"><label class="rma-dropzone" for="rma-pre-doc-file-<?php echo esc_attr($doc_key); ?>"><span>Arraste e solte ou selecione arquivo</span><input type="file" id="rma-pre-doc-file-<?php echo esc_attr($doc_key); ?>" class="rma-pre-doc-file" data-doc-key="<?php echo esc_attr($doc_key); ?>" accept="application/pdf,image/*,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" /><small class="rma-file-preview" id="rma-pre-preview-<?php echo esc_attr($doc_key); ?>">Nenhum arquivo selecionado</small></label></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <label><input type="checkbox" id="rma-consent-lgpd" required /> Concordo com LGPD</label>

            <div class="rma-actions rma-actions--left">
                <button class="rma-button" type="submit">Salvar entidade</button>
            </div>
        </form>

        <div id="rma-feedback" class="rma-feedback"></div>
    </section>

    <script>
    (function () {
        var base = <?php echo wp_json_encode($rest_base); ?>;
        var nonce = <?php echo wp_json_encode($rest_nonce); ?>;
        var contaUrl = <?php echo wp_json_encode(rma_account_setup_url()); ?>;
        try {
            var setupUrl = new URL(window.location.href);
            setupUrl.searchParams.set('rma_step', 'setup');
            window.history.replaceState({}, '', setupUrl.toString());
        } catch (e) {}

        var form = document.getElementById('rma-conta-setup-form');
        if (!form) return;

        var feedback = document.getElementById('rma-feedback');
        var fields = {
            cnpj: document.getElementById('rma-cnpj'),
            razao_social: document.getElementById('rma-razao-social'),
            nome_fantasia: document.getElementById('rma-nome-fantasia'),
            email_contato: document.getElementById('rma-email'),
            representante: document.getElementById('rma-representante'),
            endereco: document.getElementById('rma-endereco'),
            bairro: document.getElementById('rma-bairro'),
            cidade: document.getElementById('rma-cidade'),
            uf: document.getElementById('rma-uf'),
            cep: document.getElementById('rma-cep'),
            phone_country: document.getElementById('rma-phone-country'),
            telefone_contato: document.getElementById('rma-telefone'),
            data_fundacao: document.getElementById('rma-data-fundacao'),
            atividades: document.getElementById('rma-atividades'),
            consent_lgpd: document.getElementById('rma-consent-lgpd')
        };

        if (fields.telefone_contato) {
            fields.telefone_contato.addEventListener('input', function () {
                var country = fields.phone_country ? fields.phone_country.value : '55';
                var digits = normalizePhone(fields.telefone_contato.value);
                fields.telefone_contato.value = formatPhoneByCountry(country, digits);
            });
        }

        initDropzones('.rma-pre-doc-file');

        function cleanCnpj(value) { return (value || '').replace(/\D+/g, ''); }
        function showMessage(message, ok) {
            if (!feedback) return;
            feedback.innerHTML = '<div style="padding:10px;border-radius:10px;background:' + (ok ? '#edf9ec' : '#fdecec') + ';">' + message + '</div>';
        }

        function normalizePhone(raw) {
            return (raw || '').replace(/\D+/g, '');
        }

        function formatPhoneByCountry(country, digits) {
            if (country === '55') {
                if (digits.length >= 11) return '(' + digits.slice(0,2) + ') ' + digits.slice(2,7) + '-' + digits.slice(7,11);
                if (digits.length >= 10) return '(' + digits.slice(0,2) + ') ' + digits.slice(2,6) + '-' + digits.slice(6,10);
            }
            return digits;
        }

        function initDropzones(selector) {
            document.querySelectorAll(selector).forEach(function (input) {
                var zone = input.closest('.rma-dropzone');
                if (!zone) return;
                var preview = zone.querySelector('.rma-file-preview');
                function update() {
                    var file = input.files && input.files[0] ? input.files[0] : null;
                    if (preview) preview.textContent = file ? (file.name + ' • ' + Math.ceil(file.size / 1024) + ' KB') : 'Nenhum arquivo selecionado';
                }
                input.addEventListener('change', update);
                zone.addEventListener('dragover', function (event) { event.preventDefault(); zone.classList.add('is-drag'); });
                zone.addEventListener('dragleave', function () { zone.classList.remove('is-drag'); });
                zone.addEventListener('drop', function (event) {
                    event.preventDefault();
                    zone.classList.remove('is-drag');
                    if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
                        input.files = event.dataTransfer.files;
                        update();
                    }
                });
            });
        }

        function collectPreSelectedDocs() {
            var items = [];
            document.querySelectorAll('.rma-pre-doc-file').forEach(function (input) {
                if (input.files && input.files[0]) {
                    items.push({ key: input.getAttribute('data-doc-key'), file: input.files[0] });
                }
            });
            return items;
        }

        function uploadPreDocs(entityId, docs) {
            if (!docs.length) {
                return Promise.resolve();
            }

            var chain = Promise.resolve();
            docs.forEach(function (doc) {
                chain = chain.then(function () {
                    var data = new FormData();
                    data.append('document_type', doc.key);
                    data.append('file', doc.file);
                    showMessage('Enviando documento: ' + doc.key + '...', true);
                    return fetch(base + '/entities/' + entityId + '/documents', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-WP-Nonce': nonce },
                        body: data
                    })
                    .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error((result.json && result.json.message) ? result.json.message : 'Falha no upload de ' + doc.key);
                        }
                    });
                });
            });
            return chain;
        }

        document.getElementById('rma-buscar-cnpj').addEventListener('click', function () {
            var cnpj = cleanCnpj(fields.cnpj.value);
            if (!cnpj) {
                showMessage('Informe um CNPJ válido.', false);
                return;
            }

            showMessage('Consultando CNPJ...', true);
            fetch(base + '/cnpj/' + encodeURIComponent(cnpj), {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce }
            })
            .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
            .then(function (result) {
                if (!result.ok) {
                    showMessage((result.json && result.json.message) ? result.json.message : 'Não foi possível consultar o CNPJ.', false);
                    return;
                }
                fields.razao_social.value = result.json.razao_social || fields.razao_social.value;
                fields.nome_fantasia.value = result.json.nome_fantasia || fields.nome_fantasia.value;
                fields.cidade.value = result.json.cidade || fields.cidade.value;
                fields.uf.value = (result.json.uf || fields.uf.value || '').toUpperCase();
                fields.endereco.value = result.json.logradouro || fields.endereco.value;
                fields.bairro.value = result.json.bairro || fields.bairro.value;
                fields.cep.value = result.json.cep || fields.cep.value;
                showMessage('CNPJ validado e dados preenchidos.', true);
            })
            .catch(function () {
                showMessage('Erro de conexão ao consultar CNPJ.', false);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!fields.consent_lgpd.checked) {
                showMessage('É obrigatório aceitar LGPD para continuar.', false);
                return;
            }

            var docs = collectPreSelectedDocs();
            var payload = {
                cnpj: cleanCnpj(fields.cnpj.value),
                razao_social: (fields.razao_social.value || '').trim(),
                nome_fantasia: (fields.nome_fantasia.value || '').trim(),
                email_contato: (fields.email_contato.value || '').trim(),
                telefone_contato: '+' + (fields.phone_country ? fields.phone_country.value : '55') + ' ' + (fields.telefone_contato.value || '').trim(),
                cidade: (fields.cidade.value || '').trim(),
                uf: (fields.uf.value || '').trim().toUpperCase(),
                endereco: (fields.endereco.value || '').trim(),
                bairro: (fields.bairro.value || '').trim(),
                cep: (fields.cep.value || '').trim(),
                consent_lgpd: true,
                observacoes: [
                    'Representante legal: ' + (fields.representante.value || '').trim(),
                    'DDI telefone: +' + (fields.phone_country ? fields.phone_country.value : '55'),
                    'Telefone limpo: ' + normalizePhone(fields.telefone_contato.value || ''),
                    'Data de fundação: ' + (fields.data_fundacao.value || '').trim(),
                    'Atividades: ' + (fields.atividades.value || '').trim()
                ].join(' | ')
            };

            showMessage('Salvando entidade...', true);

            fetch(base + '/entities', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
            .then(function (result) {
                if (!result.ok) {
                    showMessage((result.json && result.json.message) ? result.json.message : 'Falha ao salvar entidade.', false);
                    return Promise.reject(new Error('create-failed'));
                }

                var entityId = result.json && (result.json.id || result.json.post_id) ? (result.json.id || result.json.post_id) : 0;
                if (!entityId) {
                    showMessage('Entidade criada, mas não foi possível identificar o ID (id/post_id) para upload dos documentos.', false);
                    return Promise.reject(new Error('missing-id'));
                }

                return uploadPreDocs(entityId, docs).then(function () {
                    showMessage('Entidade criada e documentos enviados. Iniciando validação de segurança...', true);
                    setTimeout(function () {
                        window.location.replace(contaUrl + (contaUrl.indexOf('?') === -1 ? '?' : '&') + 'rma_notice=entity-created');
                    }, 900);
                });
            })
            .catch(function (error) {
                if (error && (error.message === 'create-failed' || error.message === 'missing-id')) {
                    return;
                }
                showMessage(error && error.message ? error.message : 'Erro de conexão ao salvar entidade.', false);
            });
        });
    })();
    </script>
    <?php

    return (string) ob_get_clean();
});



add_shortcode('rma-minha-conta', function () {
    if (! is_user_logged_in()) {
        return '<div class="rma-feedback"><div style="padding:10px;border-radius:10px;background:#fdecec;">Você precisa estar logado para editar os dados da entidade.</div></div>';
    }

    $user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        return '<div class="rma-feedback"><div style="padding:10px;border-radius:10px;background:#fdecec;">Nenhuma entidade vinculada foi encontrada para sua conta.</div></div>';
    }

    $notice = '';
    $notice_ok = false;

    if (isset($_POST['rma_minha_conta_submit'])) {
        $nonce = isset($_POST['rma_minha_conta_nonce']) ? (string) wp_unslash($_POST['rma_minha_conta_nonce']) : '';
        if (! wp_verify_nonce($nonce, 'rma_minha_conta_update_' . $entity_id)) {
            $notice = 'Falha de segurança ao atualizar os dados.';
        } else {
            $email = sanitize_email((string) ($_POST['email_contato'] ?? ''));
            $uf = strtoupper(sanitize_text_field((string) ($_POST['uf'] ?? '')));
            if ($email !== '' && ! is_email($email)) {
                $notice = 'E-mail de contato inválido.';
            } elseif ($uf !== '' && ! preg_match('/^[A-Z]{2}$/', $uf)) {
                $notice = 'UF inválida. Use 2 letras (ex.: SP).';
            } else {
                $fields = [
                    'nome_fantasia' => sanitize_text_field((string) ($_POST['nome_fantasia'] ?? '')),
                    'email_contato' => $email,
                    'telefone_contato' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) ($_POST['telefone_contato'] ?? '')),
                    'cep' => sanitize_text_field((string) ($_POST['cep'] ?? '')),
                    'logradouro' => sanitize_text_field((string) ($_POST['logradouro'] ?? '')),
                    'numero' => sanitize_text_field((string) ($_POST['numero'] ?? '')),
                    'complemento' => sanitize_text_field((string) ($_POST['complemento'] ?? '')),
                    'bairro' => sanitize_text_field((string) ($_POST['bairro'] ?? '')),
                    'cidade' => sanitize_text_field((string) ($_POST['cidade'] ?? '')),
                    'uf' => $uf,
                ];

                foreach ($fields as $key => $val) {
                    update_post_meta($entity_id, $key, $val);
                }

                $descricao = sanitize_textarea_field((string) ($_POST['descricao'] ?? ''));
                wp_update_post([
                    'ID' => $entity_id,
                    'post_content' => $descricao,
                ]);

                $notice = 'Dados da entidade atualizados com sucesso.';
                $notice_ok = true;
            }
        }
    }

    $values = [
        'razao_social' => (string) get_post_meta($entity_id, 'razao_social', true),
        'nome_fantasia' => (string) get_post_meta($entity_id, 'nome_fantasia', true),
        'email_contato' => (string) get_post_meta($entity_id, 'email_contato', true),
        'telefone_contato' => (string) get_post_meta($entity_id, 'telefone_contato', true),
        'cep' => (string) get_post_meta($entity_id, 'cep', true),
        'logradouro' => (string) get_post_meta($entity_id, 'logradouro', true),
        'numero' => (string) get_post_meta($entity_id, 'numero', true),
        'complemento' => (string) get_post_meta($entity_id, 'complemento', true),
        'bairro' => (string) get_post_meta($entity_id, 'bairro', true),
        'cidade' => (string) get_post_meta($entity_id, 'cidade', true),
        'uf' => (string) get_post_meta($entity_id, 'uf', true),
        'descricao' => (string) get_post_field('post_content', $entity_id),
    ];

    ob_start();
    ?>
    <section class="rma-glass-card" style="margin:20px 0;">
        <h2 class="rma-glass-title">Minha Conta da Entidade</h2>
        <p class="rma-glass-subtitle">Atualize endereço e contatos para manter os dados da entidade e do mapa sempre corretos.</p>

        <?php if ($notice !== '') : ?>
            <div class="rma-feedback" style="margin-bottom:12px;">
                <div style="padding:10px;border-radius:10px;background:<?php echo $notice_ok ? '#edf9ec' : '#fdecec'; ?>;">
                    <?php echo esc_html($notice); ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="rma-grid rma-grid-2" style="gap:12px;">
            <?php wp_nonce_field('rma_minha_conta_update_' . $entity_id, 'rma_minha_conta_nonce'); ?>
            <div>
                <label>Razão social</label>
                <input type="text" value="<?php echo esc_attr($values['razao_social']); ?>" readonly>
            </div>
            <div>
                <label>Nome fantasia</label>
                <input type="text" name="nome_fantasia" value="<?php echo esc_attr($values['nome_fantasia']); ?>">
            </div>
            <div>
                <label>E-mail de contato</label>
                <input type="email" name="email_contato" value="<?php echo esc_attr($values['email_contato']); ?>">
            </div>
            <div>
                <label>Telefone</label>
                <input type="text" name="telefone_contato" value="<?php echo esc_attr($values['telefone_contato']); ?>">
            </div>
            <div>
                <label>CEP</label>
                <input type="text" name="cep" value="<?php echo esc_attr($values['cep']); ?>">
            </div>
            <div>
                <label>Logradouro</label>
                <input type="text" name="logradouro" value="<?php echo esc_attr($values['logradouro']); ?>">
            </div>
            <div>
                <label>Número</label>
                <input type="text" name="numero" value="<?php echo esc_attr($values['numero']); ?>">
            </div>
            <div>
                <label>Complemento</label>
                <input type="text" name="complemento" value="<?php echo esc_attr($values['complemento']); ?>">
            </div>
            <div>
                <label>Bairro</label>
                <input type="text" name="bairro" value="<?php echo esc_attr($values['bairro']); ?>">
            </div>
            <div>
                <label>Cidade</label>
                <input type="text" name="cidade" value="<?php echo esc_attr($values['cidade']); ?>">
            </div>
            <div>
                <label>UF</label>
                <input type="text" name="uf" maxlength="2" value="<?php echo esc_attr(strtoupper($values['uf'])); ?>">
            </div>
            <div style="grid-column:1 / -1;">
                <label>Descrição institucional</label>
                <textarea name="descricao" rows="4"><?php echo esc_textarea($values['descricao']); ?></textarea>
            </div>
            <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;">
                <button class="rma-button btn-rma-primary" type="submit" name="rma_minha_conta_submit" value="1">Salvar dados da entidade</button>
            </div>
        </form>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (! is_user_logged_in()) {
        return;
    }

    $request_uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
    $request_path = is_string($request_path) ? untrailingslashit($request_path) : '';

    if ($request_path === '') {
        return;
    }

    $account_path = untrailingslashit((string) rma_account_setup_path());
    $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));
    $checkout_path = untrailingslashit((string) wp_parse_url(apply_filters('rma_checkout_url', home_url('/checkout/')), PHP_URL_PATH));

    if ($account_path !== '' && $request_path === $account_path) {
        return;
    }

    if ($dashboard_path !== '' && $request_path === $dashboard_path) {
        $entity_id = rma_get_entity_id_by_author(get_current_user_id());
        if ($entity_id > 0 && rma_theme_entity_has_started_or_completed_dues_checkout($entity_id)) {
            rma_flow_debug_log('allow_dashboard_due_to_dues_checkout', [
                'request_path' => $request_path,
                'entity_id' => $entity_id,
            ]);
            return;
        }

        rma_flow_debug_log('redirect_dashboard_blocked_until_dues_checkout', [
            'request_path' => $request_path,
            'entity_id' => $entity_id,
            'to' => rma_account_setup_url(),
        ]);
        wp_safe_redirect(rma_account_setup_url());
        exit;
    }

    foreach (['/login', '/register', '/wp-login.php'] as $safe_suffix) {
        if (substr($request_path, -strlen($safe_suffix)) === $safe_suffix) {
            return;
        }
    }

    $current_user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($current_user_id);
    if ($entity_id <= 0) {
        rma_flow_debug_log('redirect_no_entity', ['to' => rma_account_setup_url()]);
        wp_safe_redirect(rma_account_setup_url());
        exit;
    }

    $governance = (string) get_post_meta($entity_id, 'governance_status', true);
    $finance = (string) get_post_meta($entity_id, 'finance_status', true);
    $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);

    $is_checkout_request = ($checkout_path !== '' && $request_path === $checkout_path);
    $requested_product_id = absint((int) ($_GET['add-to-cart'] ?? 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $dues_product_ids = function_exists('rma_get_annual_dues_product_ids') ? rma_get_annual_dues_product_ids() : [];
    $is_dues_add_to_cart_request = ($requested_product_id > 0 && in_array($requested_product_id, $dues_product_ids, true));

    if ($is_checkout_request || $is_dues_add_to_cart_request) {
        rma_flow_debug_log('allow_checkout_request', [
            'request_path' => $request_path,
            'entity_id' => $entity_id,
            'add_to_cart' => $requested_product_id,
            'governance' => $governance,
            'finance' => $finance,
            'docs_status' => $docs_status,
        ]);
        return;
    }

    $all_steps_done = ($governance === 'aprovado' && $finance === 'adimplente' && in_array($docs_status, ['enviado', 'aprovado', 'validado', 'aceito'], true));
    if ($all_steps_done) {
        return;
    }

    $allowed_paths = array_filter(array_map('untrailingslashit', [
        $account_path,
        (string) wp_parse_url(home_url('/documentos/'), PHP_URL_PATH),
        (string) wp_parse_url(home_url('/financeiro/'), PHP_URL_PATH),
        (string) wp_parse_url(home_url('/status/'), PHP_URL_PATH),
    ]));

    foreach ($allowed_paths as $allowed_path) {
        if ($allowed_path !== '' && $request_path === $allowed_path) {
            return;
        }
    }

    rma_flow_debug_log('redirect_incomplete_flow', [
        'to' => rma_account_setup_url(),
        'request_path' => $request_path,
        'entity_id' => $entity_id,
        'governance' => $governance,
        'finance' => $finance,
        'docs_status' => $docs_status,
        'allowed_paths' => array_values($allowed_paths),
    ]);
    wp_safe_redirect(rma_account_setup_url());
    exit;
}, 20);

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (! is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        return;
    }

    $governance = strtolower(trim((string) get_post_meta($entity_id, 'governance_status', true)));
    $finance = strtolower(trim((string) get_post_meta($entity_id, 'finance_status', true)));
    if ($governance !== 'aprovado' || $finance !== 'adimplente') {
        return;
    }

    if (rma_entity_has_uploaded_logo($entity_id, $user_id)) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
    $request_path = is_string($request_path) ? untrailingslashit($request_path) : '';

    $dashboard_url = home_url('/dashboard/');
    $dashboard_path = (string) wp_parse_url($dashboard_url, PHP_URL_PATH);
    $dashboard_path = untrailingslashit($dashboard_path);
    $ext = isset($_GET['ext']) ? sanitize_key((string) $_GET['ext']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ($request_path !== $dashboard_path || $ext !== 'edit-profile') {
        $target = add_query_arg([
            'ext' => 'edit-profile',
            'rma_logo_required' => '1',
        ], $dashboard_url);
        rma_flow_debug_log('redirect_logo_required_edit_profile', [
            'request_path' => $request_path,
            'dashboard_path' => $dashboard_path,
            'ext' => $ext,
            'target' => $target,
            'entity_id' => $entity_id,
        ]);
        wp_safe_redirect($target);
        exit;
    }
}, 25);

add_action('wp_footer', function (): void {
    if (! is_user_logged_in()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));
    if ($dashboard_path === '' || $request_path === '' || strpos(untrailingslashit($request_path), $dashboard_path) !== 0) {
        return;
    }

    $ext = isset($_GET['ext']) ? sanitize_key((string) $_GET['ext']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        return;
    }

    if (rma_entity_has_uploaded_logo($entity_id, $user_id)) {
        return;
    }

    $governance = strtolower(trim((string) get_post_meta($entity_id, 'governance_status', true)));
    $finance = strtolower(trim((string) get_post_meta($entity_id, 'finance_status', true)));
    $is_strict_required = ($governance === 'aprovado' && $finance === 'adimplente');

    $entity_name = get_the_title($entity_id);
    if (! is_string($entity_name) || $entity_name === '') {
        $entity_name = 'sua entidade';
    }
    ?>
    <script>
    (function(){
        var entityName = <?php echo wp_json_encode($entity_name); ?>;
        var ext = <?php echo wp_json_encode($ext); ?>;
        var strictRequired = <?php echo $is_strict_required ? 'true' : 'false'; ?>;

        function findEmailAlert(){
            var nodes = Array.prototype.slice.call(document.querySelectorAll('.alert,.notice,.woocommerce-message,.card,.content-wrapper > div,.main-panel > div,.content-wrapper section,.main-panel section'));
            return nodes.find(function(node){
                var txt = (node.textContent || '').replace(/\s+/g, ' ').trim();
                return /endere[çc]o de e-?mail n[ãa]o foi verificado/i.test(txt);
            }) || null;
        }

        function createAlert(){
            var alert = document.createElement('div');
            alert.id = 'rma-logo-required-alert';
            alert.style.cssText = 'margin:12px 0 18px;padding:14px 16px;border-radius:10px;border:1px solid rgba(185,28,28,.28);background:#fff7f7;color:#1f2937;box-shadow:0 6px 16px rgba(15,23,42,.06);';

            if (strictRequired) {
                alert.innerHTML = '<strong style="display:block;color:#991b1b;margin-bottom:4px">Ação obrigatória: inclua o logotipo de ' + entityName + ' para concluir a ativação.</strong><span style="display:block;line-height:1.45;color:#334155">Faça upload do logotipo oficial em <strong>Minha Conta → Editar perfil</strong> e clique em <strong>Salvar dados</strong> para liberar o fluxo completo.</span>';
            } else {
                alert.innerHTML = '<strong style="display:block;color:#9a3412;margin-bottom:4px">Importante: complete o perfil de ' + entityName + ' com o logotipo oficial.</strong><span style="display:block;line-height:1.45;color:#334155">Antecipe esta etapa em <strong>Minha Conta → Editar perfil</strong>. O logotipo será obrigatório para concluir a ativação final da entidade.</span>';
                alert.style.borderColor = 'rgba(234,88,12,.28)';
                alert.style.background = '#fffaf5';
            }

            return alert;
        }

        function mountAlert(){
            if (document.getElementById('rma-logo-required-alert')) {
                return true;
            }
            var panel = document.querySelector('.main-panel') || document.querySelector('.content-wrapper');
            if (!panel) {
                return false;
            }

            var alert = createAlert();
            var emailAlert = findEmailAlert();
            if (emailAlert && emailAlert.parentNode) {
                emailAlert.insertAdjacentElement('afterend', alert);
            } else {
                panel.insertBefore(alert, panel.firstChild);
            }

            if (ext === 'edit-profile') {
                var logoInput = document.getElementById('emp_profile_pic');
                var logoLabel = Array.prototype.slice.call(document.querySelectorAll('label')).find(function(node){
                    return (node.textContent || '').trim().toLowerCase() === 'logotipo';
                });
                var logoContainer = logoLabel ? logoLabel.closest('.form-group') : null;

                if (logoContainer) {
                    logoContainer.style.border = '1px solid rgba(185,28,28,.24)';
                    logoContainer.style.borderRadius = '12px';
                    logoContainer.style.padding = '10px';
                    logoContainer.style.background = 'rgba(254,242,242,.45)';
                }

                if (logoInput) {
                    logoInput.scrollIntoView({behavior:"smooth", block:"center"});
                    logoInput.focus();
                }
            }

            return true;
        }

        if (!mountAlert()) {
            var attempts = 0;
            var timer = setInterval(function(){
                attempts += 1;
                if (mountAlert() || attempts >= 12) {
                    clearInterval(timer);
                }
            }, 500);
        }
    })();
    </script>
    <?php
}, 99);
// Remove "Obrigado por criar com WordPress"
add_filter('admin_footer_text', '__return_empty_string');

// Remove a versão do WordPress no rodapé do admin
add_filter('update_footer', '__return_empty_string', 11);
function rma_substituir_processing() {
?>
<script>
document.addEventListener("DOMContentLoaded", function() {

    function substituirTexto() {
        document.querySelectorAll("body *").forEach(function(el) {
            if (el.children.length === 0 && el.textContent.trim() === "PROCESSING") {
                el.textContent = "Pago";
            }
        });
    }

    substituirTexto();

    const observer = new MutationObserver(substituirTexto);
    observer.observe(document.body, { childList: true, subtree: true });

});
</script>
<?php
}
add_action('wp_footer', 'rma_substituir_processing');
function rma_custom_css_text_primary() {
    echo '<style>
        .text-primary {
            color: #7bad39 !important;
        }
    </style>';
}
add_action('wp_head', 'rma_custom_css_text_primary');


if (!function_exists('rma_fix_map_showcase_pin_render')) {
	function rma_fix_map_showcase_pin_render()
	{
		if (!shortcode_exists('rma_map_showcase')) {
			return;
		}

		remove_shortcode('rma_map_showcase');
		add_shortcode('rma_map_showcase', function ($atts = array()) {
			$atts = shortcode_atts(array(
				'per_page' => 12,
			), $atts, 'rma_map_showcase');

			$inner = do_shortcode('[rma_map_directory per_page="' . absint($atts['per_page']) . '"]');
			ob_start();
			?>
			<div class="rma-map-showcase-shortcode">
				<style>
					.rma-map-showcase-shortcode .rma-map-intro,
					.rma-map-showcase-shortcode .rma-map-kpis{display:none !important;}
					.rma-map-showcase-shortcode .rma-map-directory{padding:16px;border-radius:18px;border:1px solid rgba(15,47,74,.15);background:linear-gradient(180deg,#f7fbff 0%,#f2f8fc 100%);}
					.rma-map-showcase-shortcode .rma-map-filters{display:grid;grid-template-columns:minmax(220px,1.45fr) minmax(180px,1fr) minmax(180px,1fr) minmax(170px,.95fr) minmax(170px,.9fr);gap:10px;align-items:stretch;margin:0 0 14px;padding:12px;border:1px solid #dbe7f3;border-radius:14px;background:rgba(255,255,255,.92);box-shadow:0 8px 20px rgba(15,47,74,.08)}
					.rma-map-showcase-shortcode .rma-map-filters input,
					.rma-map-showcase-shortcode .rma-map-filters select{height:44px;line-height:44px;background-color:#ffffff;border:none;border-radius:15px;border:1px solid #c8d9ea;padding:0 14px;color:#16324a;font-weight:600}
					.rma-map-showcase-shortcode .rma-map-filters input:focus,
					.rma-map-showcase-shortcode .rma-map-filters select:focus{outline:none;border-color:#7bad39;box-shadow:0 0 0 3px rgba(123,173,57,.18)}
					.rma-map-showcase-shortcode .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:43px;background-color:#ffffff;border:none;border-radius:15px;border:1px solid #c8d9ea}
					.rma-map-showcase-shortcode .select2-container .select2-selection--single{height:44px;border:1px solid #c8d9ea;border-radius:15px;background:#fff}
					.rma-map-showcase-shortcode .rma-map-filters [name="area"]{display:none !important}
					.rma-map-showcase-shortcode .rma-map-state-chip{background:#f8fcff;border:1px solid #bcd2e6;border-radius:12px;color:#1a4668;font-weight:700;padding:6px 10px}
					.rma-map-showcase-shortcode .rma-map-state-chip.is-active{background:linear-gradient(135deg,#dff3ff,#ecfff6);border-color:#7bad39;color:#0f2f4a}
					.rma-map-showcase-shortcode .rma-map-filters .rma-map-apply{height:44px;line-height:44px;border-radius:12px;background:linear-gradient(135deg, #7bad39, #28f3a8);color:#fff;font-weight:800;letter-spacing:.2px;width:100%;white-space:nowrap}
					.rma-map-showcase-shortcode .rma-map-feedback{margin:0 0 10px;padding:8px 10px;border-left:3px solid #7bad39;background:rgba(123,173,57,.08);border-radius:8px;color:#16324a;font-weight:600}
					.rma-map-showcase-shortcode #rma-map-pins .rma-map-pin path{fill:#ff4d00 !important;stroke:#0f7a36 !important;stroke-width:1.25 !important}
					.rma-map-showcase-shortcode #rma-map-pins .rma-map-pin circle{fill:#ecfdf3 !important}
					.rma-map-showcase-shortcode #rma-map-tooltip,
					.rma-map-showcase-shortcode .rma-map-tooltip{background:#ffffff !important;color:#111111 !important;border:1px solid rgba(15,47,74,.22) !important}
					.rma-map-showcase-shortcode #rma-map-tooltip *,
					.rma-map-showcase-shortcode .rma-map-tooltip *{color:#111111 !important}
					@media (max-width:1100px){.rma-map-showcase-shortcode .rma-map-filters{grid-template-columns:repeat(3,minmax(170px,1fr));}}
					@media (max-width:760px){.rma-map-showcase-shortcode .rma-map-filters{grid-template-columns:repeat(2,minmax(150px,1fr));}}
					@media (max-width:560px){.rma-map-showcase-shortcode .rma-map-filters{grid-template-columns:1fr;}}
					.rma-map-showcase-shortcode .rma-map-brazil-grid{margin-top:0;}
					.rma-map-showcase-shortcode .rma-map-states{justify-content:center;margin-top:14px;}
					.rma-map-showcase-shortcode .rma-map-state-chip{min-width:56px;text-align:center;}
				</style>
				<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
			return (string) ob_get_clean();
		});
	}
	add_action('init', 'rma_fix_map_showcase_pin_render', 1000);
}add_action('wp_head', function () {
    if (is_page(12)) {
        echo '<style>
            body.page-id-12 .fr-list-product {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                overflow: hidden !important;
            }
        </style>';
    }
});
