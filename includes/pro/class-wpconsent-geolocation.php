<?php
/**
 * Geolocation settings page.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Geolocation.
 */
class WPConsent_Geolocation {

	/**
	 * Remote API URL.
	 *
	 * @var string
	 */
	public $remote_api_url = 'https://geo.wpconsent.com/v3/geolocate/json/';

	/**
	 * Whether geolocation is enabled.
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * The enabled groups data.
	 *
	 * @var array
	 */
	protected $groups;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_filter( 'wpconsent_get_option_enable_consent_banner', array( $this, 'maybe_force_enable_banner' ) );
		add_filter( 'wpconsent_get_option_enable_script_blocking', array(
			$this,
			'maybe_force_enable_script_blocking'
		) );
		add_filter( 'wpconsent_get_option_default_allow', array(
			$this,
			'maybe_force_default_allow'
		) );
		add_filter( 'wpconsent_frontend_js_data', array( $this, 'frontend_js_data' ) );
	}

	/**
	 * Get the geolocation groups data.
	 *
	 * @return array
	 */
	public function get_groups() {
		if ( ! isset( $this->groups ) ) {
			$this->groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );
		}

		return $this->groups;
	}

	/**
	 * Is the geolocation enabled?
	 *
	 * @return bool
	 */
	public function enabled() {
		if ( ! isset( $this->enabled ) ) {
			$this->enabled = ! empty( $this->get_groups() );
		}

		return $this->enabled;
	}

	/**
	 * Get an array of continents.
	 *
	 * @return array.
	 */
	public static function get_continents() {
		return array(
			'AF' => array(
				'name'      => __( 'Africa', 'wpconsent-premium' ),
				'countries' => array(
					'AO',
					'BF',
					'BI',
					'BJ',
					'BW',
					'CD',
					'CF',
					'CG',
					'CI',
					'CM',
					'CV',
					'DJ',
					'DZ',
					'EG',
					'EH',
					'ER',
					'ET',
					'GA',
					'GH',
					'GM',
					'GN',
					'GQ',
					'GW',
					'KE',
					'KM',
					'LR',
					'LS',
					'LY',
					'MA',
					'MG',
					'ML',
					'MR',
					'MU',
					'MW',
					'MZ',
					'NA',
					'NE',
					'NG',
					'RE',
					'RW',
					'SC',
					'SD',
					'SH',
					'SL',
					'SN',
					'SO',
					'SS',
					'ST',
					'SZ',
					'TD',
					'TG',
					'TN',
					'TZ',
					'UG',
					'YT',
					'ZA',
					'ZM',
					'ZW',
				),
			),
			'AN' => array(
				'name'      => __( 'Antarctica', 'wpconsent-premium' ),
				'countries' => array(
					'AQ',
					'BV',
					'GS',
					'HM',
					'TF',
				),
			),
			'AS' => array(
				'name'      => __( 'Asia', 'wpconsent-premium' ),
				'countries' => array(
					'AE',
					'AF',
					'AM',
					'AZ',
					'BD',
					'BH',
					'BN',
					'BT',
					'CC',
					'CN',
					'CX',
					'CY',
					'GE',
					'HK',
					'ID',
					'IL',
					'IN',
					'IO',
					'IQ',
					'IR',
					'JO',
					'JP',
					'KG',
					'KH',
					'KP',
					'KR',
					'KW',
					'KZ',
					'LA',
					'LB',
					'LK',
					'MM',
					'MN',
					'MO',
					'MV',
					'MY',
					'NP',
					'OM',
					'PH',
					'PK',
					'PS',
					'QA',
					'SA',
					'SG',
					'SY',
					'TH',
					'TJ',
					'TL',
					'TM',
					'TW',
					'UZ',
					'VN',
					'YE',
				),
			),
			'EU' => array(
				'name'      => __( 'Europe', 'wpconsent-premium' ),
				'countries' => array(
					'AD',
					'AL',
					'AT',
					'AX',
					'BA',
					'BE',
					'BG',
					'BY',
					'CH',
					'CZ',
					'DE',
					'DK',
					'EE',
					'ES',
					'FI',
					'FO',
					'FR',
					'GB',
					'GG',
					'GI',
					'GR',
					'HR',
					'HU',
					'IE',
					'IM',
					'IS',
					'IT',
					'JE',
					'LI',
					'LT',
					'LU',
					'LV',
					'MC',
					'MD',
					'ME',
					'MK',
					'MT',
					'NL',
					'NO',
					'PL',
					'PT',
					'RO',
					'RS',
					'RU',
					'SE',
					'SI',
					'SJ',
					'SK',
					'SM',
					'TR',
					'UA',
					'VA',
				),
			),
			'NA' => array(
				'name'      => __( 'North America', 'wpconsent-premium' ),
				'countries' => array(
					'AG',
					'AI',
					'AW',
					'BB',
					'BL',
					'BM',
					'BQ',
					'BS',
					'BZ',
					'CA',
					'CR',
					'CU',
					'CW',
					'DM',
					'DO',
					'GD',
					'GL',
					'GP',
					'GT',
					'HN',
					'HT',
					'JM',
					'KN',
					'KY',
					'LC',
					'MF',
					'MQ',
					'MS',
					'MX',
					'NI',
					'PA',
					'PM',
					'PR',
					'SV',
					'SX',
					'TC',
					'TT',
					'US',
					'VC',
					'VG',
					'VI',
				),
			),
			'OC' => array(
				'name'      => __( 'Oceania', 'wpconsent-premium' ),
				'countries' => array(
					'AS',
					'AU',
					'CK',
					'FJ',
					'FM',
					'GU',
					'KI',
					'MH',
					'MP',
					'NC',
					'NF',
					'NR',
					'NU',
					'NZ',
					'PF',
					'PG',
					'PN',
					'PW',
					'SB',
					'TK',
					'TO',
					'TV',
					'UM',
					'VU',
					'WF',
					'WS',
				),
			),
			'SA' => array(
				'name'      => __( 'South America', 'wpconsent-premium' ),
				'countries' => array(
					'AR',
					'BO',
					'BR',
					'CL',
					'CO',
					'EC',
					'FK',
					'GF',
					'GY',
					'PE',
					'PY',
					'SR',
					'UY',
					'VE',
				),
			),
		);
	}

	/**
	 * Get an array of countries.
	 *
	 * @return array.
	 */
	public static function get_countries() {
		return array(
			'AF' => __( 'Afghanistan', 'wpconsent-premium' ),
			'AX' => __( 'Åland Islands', 'wpconsent-premium' ),
			'AL' => __( 'Albania', 'wpconsent-premium' ),
			'DZ' => __( 'Algeria', 'wpconsent-premium' ),
			'AS' => __( 'American Samoa', 'wpconsent-premium' ),
			'AD' => __( 'Andorra', 'wpconsent-premium' ),
			'AO' => __( 'Angola', 'wpconsent-premium' ),
			'AI' => __( 'Anguilla', 'wpconsent-premium' ),
			'AQ' => __( 'Antarctica', 'wpconsent-premium' ),
			'AG' => __( 'Antigua and Barbuda', 'wpconsent-premium' ),
			'AR' => __( 'Argentina', 'wpconsent-premium' ),
			'AM' => __( 'Armenia', 'wpconsent-premium' ),
			'AW' => __( 'Aruba', 'wpconsent-premium' ),
			'AU' => __( 'Australia', 'wpconsent-premium' ),
			'AT' => __( 'Austria', 'wpconsent-premium' ),
			'AZ' => __( 'Azerbaijan', 'wpconsent-premium' ),
			'BS' => __( 'Bahamas', 'wpconsent-premium' ),
			'BH' => __( 'Bahrain', 'wpconsent-premium' ),
			'BD' => __( 'Bangladesh', 'wpconsent-premium' ),
			'BB' => __( 'Barbados', 'wpconsent-premium' ),
			'BY' => __( 'Belarus', 'wpconsent-premium' ),
			'BE' => __( 'Belgium', 'wpconsent-premium' ),
			'PW' => __( 'Belau', 'wpconsent-premium' ),
			'BZ' => __( 'Belize', 'wpconsent-premium' ),
			'BJ' => __( 'Benin', 'wpconsent-premium' ),
			'BM' => __( 'Bermuda', 'wpconsent-premium' ),
			'BT' => __( 'Bhutan', 'wpconsent-premium' ),
			'BO' => __( 'Bolivia', 'wpconsent-premium' ),
			'BQ' => __( 'Bonaire, Saint Eustatius and Saba', 'wpconsent-premium' ),
			'BA' => __( 'Bosnia and Herzegovina', 'wpconsent-premium' ),
			'BW' => __( 'Botswana', 'wpconsent-premium' ),
			'BV' => __( 'Bouvet Island', 'wpconsent-premium' ),
			'BR' => __( 'Brazil', 'wpconsent-premium' ),
			'IO' => __( 'British Indian Ocean Territory', 'wpconsent-premium' ),
			'BN' => __( 'Brunei', 'wpconsent-premium' ),
			'BG' => __( 'Bulgaria', 'wpconsent-premium' ),
			'BF' => __( 'Burkina Faso', 'wpconsent-premium' ),
			'BI' => __( 'Burundi', 'wpconsent-premium' ),
			'KH' => __( 'Cambodia', 'wpconsent-premium' ),
			'CM' => __( 'Cameroon', 'wpconsent-premium' ),
			'CA' => __( 'Canada', 'wpconsent-premium' ),
			'CV' => __( 'Cape Verde', 'wpconsent-premium' ),
			'KY' => __( 'Cayman Islands', 'wpconsent-premium' ),
			'CF' => __( 'Central African Republic', 'wpconsent-premium' ),
			'TD' => __( 'Chad', 'wpconsent-premium' ),
			'CL' => __( 'Chile', 'wpconsent-premium' ),
			'CN' => __( 'China', 'wpconsent-premium' ),
			'CX' => __( 'Christmas Island', 'wpconsent-premium' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'wpconsent-premium' ),
			'CO' => __( 'Colombia', 'wpconsent-premium' ),
			'KM' => __( 'Comoros', 'wpconsent-premium' ),
			'CG' => __( 'Congo (Brazzaville)', 'wpconsent-premium' ),
			'CD' => __( 'Congo (Kinshasa)', 'wpconsent-premium' ),
			'CK' => __( 'Cook Islands', 'wpconsent-premium' ),
			'CR' => __( 'Costa Rica', 'wpconsent-premium' ),
			'HR' => __( 'Croatia', 'wpconsent-premium' ),
			'CU' => __( 'Cuba', 'wpconsent-premium' ),
			'CW' => __( 'Cura&ccedil;ao', 'wpconsent-premium' ),
			'CY' => __( 'Cyprus', 'wpconsent-premium' ),
			'CZ' => __( 'Czech Republic', 'wpconsent-premium' ),
			'DK' => __( 'Denmark', 'wpconsent-premium' ),
			'DJ' => __( 'Djibouti', 'wpconsent-premium' ),
			'DM' => __( 'Dominica', 'wpconsent-premium' ),
			'DO' => __( 'Dominican Republic', 'wpconsent-premium' ),
			'EC' => __( 'Ecuador', 'wpconsent-premium' ),
			'EG' => __( 'Egypt', 'wpconsent-premium' ),
			'SV' => __( 'El Salvador', 'wpconsent-premium' ),
			'GQ' => __( 'Equatorial Guinea', 'wpconsent-premium' ),
			'ER' => __( 'Eritrea', 'wpconsent-premium' ),
			'EE' => __( 'Estonia', 'wpconsent-premium' ),
			'ET' => __( 'Ethiopia', 'wpconsent-premium' ),
			'FK' => __( 'Falkland Islands', 'wpconsent-premium' ),
			'FO' => __( 'Faroe Islands', 'wpconsent-premium' ),
			'FJ' => __( 'Fiji', 'wpconsent-premium' ),
			'FI' => __( 'Finland', 'wpconsent-premium' ),
			'FR' => __( 'France', 'wpconsent-premium' ),
			'GF' => __( 'French Guiana', 'wpconsent-premium' ),
			'PF' => __( 'French Polynesia', 'wpconsent-premium' ),
			'TF' => __( 'French Southern Territories', 'wpconsent-premium' ),
			'GA' => __( 'Gabon', 'wpconsent-premium' ),
			'GM' => __( 'Gambia', 'wpconsent-premium' ),
			'GE' => __( 'Georgia', 'wpconsent-premium' ),
			'DE' => __( 'Germany', 'wpconsent-premium' ),
			'GH' => __( 'Ghana', 'wpconsent-premium' ),
			'GI' => __( 'Gibraltar', 'wpconsent-premium' ),
			'GR' => __( 'Greece', 'wpconsent-premium' ),
			'GL' => __( 'Greenland', 'wpconsent-premium' ),
			'GD' => __( 'Grenada', 'wpconsent-premium' ),
			'GP' => __( 'Guadeloupe', 'wpconsent-premium' ),
			'GU' => __( 'Guam', 'wpconsent-premium' ),
			'GT' => __( 'Guatemala', 'wpconsent-premium' ),
			'GG' => __( 'Guernsey', 'wpconsent-premium' ),
			'GN' => __( 'Guinea', 'wpconsent-premium' ),
			'GW' => __( 'Guinea-Bissau', 'wpconsent-premium' ),
			'GY' => __( 'Guyana', 'wpconsent-premium' ),
			'HT' => __( 'Haiti', 'wpconsent-premium' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'wpconsent-premium' ),
			'HN' => __( 'Honduras', 'wpconsent-premium' ),
			'HK' => __( 'Hong Kong', 'wpconsent-premium' ),
			'HU' => __( 'Hungary', 'wpconsent-premium' ),
			'IS' => __( 'Iceland', 'wpconsent-premium' ),
			'IN' => __( 'India', 'wpconsent-premium' ),
			'ID' => __( 'Indonesia', 'wpconsent-premium' ),
			'IR' => __( 'Iran', 'wpconsent-premium' ),
			'IQ' => __( 'Iraq', 'wpconsent-premium' ),
			'IE' => __( 'Ireland', 'wpconsent-premium' ),
			'IM' => __( 'Isle of Man', 'wpconsent-premium' ),
			'IL' => __( 'Israel', 'wpconsent-premium' ),
			'IT' => __( 'Italy', 'wpconsent-premium' ),
			'CI' => __( 'Ivory Coast', 'wpconsent-premium' ),
			'JM' => __( 'Jamaica', 'wpconsent-premium' ),
			'JP' => __( 'Japan', 'wpconsent-premium' ),
			'JE' => __( 'Jersey', 'wpconsent-premium' ),
			'JO' => __( 'Jordan', 'wpconsent-premium' ),
			'KZ' => __( 'Kazakhstan', 'wpconsent-premium' ),
			'KE' => __( 'Kenya', 'wpconsent-premium' ),
			'KI' => __( 'Kiribati', 'wpconsent-premium' ),
			'KW' => __( 'Kuwait', 'wpconsent-premium' ),
			'KG' => __( 'Kyrgyzstan', 'wpconsent-premium' ),
			'LA' => __( 'Laos', 'wpconsent-premium' ),
			'LV' => __( 'Latvia', 'wpconsent-premium' ),
			'LB' => __( 'Lebanon', 'wpconsent-premium' ),
			'LS' => __( 'Lesotho', 'wpconsent-premium' ),
			'LR' => __( 'Liberia', 'wpconsent-premium' ),
			'LY' => __( 'Libya', 'wpconsent-premium' ),
			'LI' => __( 'Liechtenstein', 'wpconsent-premium' ),
			'LT' => __( 'Lithuania', 'wpconsent-premium' ),
			'LU' => __( 'Luxembourg', 'wpconsent-premium' ),
			'MO' => __( 'Macao', 'wpconsent-premium' ),
			'MK' => __( 'North Macedonia', 'wpconsent-premium' ),
			'MG' => __( 'Madagascar', 'wpconsent-premium' ),
			'MW' => __( 'Malawi', 'wpconsent-premium' ),
			'MY' => __( 'Malaysia', 'wpconsent-premium' ),
			'MV' => __( 'Maldives', 'wpconsent-premium' ),
			'ML' => __( 'Mali', 'wpconsent-premium' ),
			'MT' => __( 'Malta', 'wpconsent-premium' ),
			'MH' => __( 'Marshall Islands', 'wpconsent-premium' ),
			'MQ' => __( 'Martinique', 'wpconsent-premium' ),
			'MR' => __( 'Mauritania', 'wpconsent-premium' ),
			'MU' => __( 'Mauritius', 'wpconsent-premium' ),
			'YT' => __( 'Mayotte', 'wpconsent-premium' ),
			'MX' => __( 'Mexico', 'wpconsent-premium' ),
			'FM' => __( 'Micronesia', 'wpconsent-premium' ),
			'MD' => __( 'Moldova', 'wpconsent-premium' ),
			'MC' => __( 'Monaco', 'wpconsent-premium' ),
			'MN' => __( 'Mongolia', 'wpconsent-premium' ),
			'ME' => __( 'Montenegro', 'wpconsent-premium' ),
			'MS' => __( 'Montserrat', 'wpconsent-premium' ),
			'MA' => __( 'Morocco', 'wpconsent-premium' ),
			'MZ' => __( 'Mozambique', 'wpconsent-premium' ),
			'MM' => __( 'Myanmar', 'wpconsent-premium' ),
			'NA' => __( 'Namibia', 'wpconsent-premium' ),
			'NR' => __( 'Nauru', 'wpconsent-premium' ),
			'NP' => __( 'Nepal', 'wpconsent-premium' ),
			'NL' => __( 'Netherlands', 'wpconsent-premium' ),
			'NC' => __( 'New Caledonia', 'wpconsent-premium' ),
			'NZ' => __( 'New Zealand', 'wpconsent-premium' ),
			'NI' => __( 'Nicaragua', 'wpconsent-premium' ),
			'NE' => __( 'Niger', 'wpconsent-premium' ),
			'NG' => __( 'Nigeria', 'wpconsent-premium' ),
			'NU' => __( 'Niue', 'wpconsent-premium' ),
			'NF' => __( 'Norfolk Island', 'wpconsent-premium' ),
			'MP' => __( 'Northern Mariana Islands', 'wpconsent-premium' ),
			'KP' => __( 'North Korea', 'wpconsent-premium' ),
			'NO' => __( 'Norway', 'wpconsent-premium' ),
			'OM' => __( 'Oman', 'wpconsent-premium' ),
			'PK' => __( 'Pakistan', 'wpconsent-premium' ),
			'PS' => __( 'Palestinian Territory', 'wpconsent-premium' ),
			'PA' => __( 'Panama', 'wpconsent-premium' ),
			'PG' => __( 'Papua New Guinea', 'wpconsent-premium' ),
			'PY' => __( 'Paraguay', 'wpconsent-premium' ),
			'PE' => __( 'Peru', 'wpconsent-premium' ),
			'PH' => __( 'Philippines', 'wpconsent-premium' ),
			'PN' => __( 'Pitcairn', 'wpconsent-premium' ),
			'PL' => __( 'Poland', 'wpconsent-premium' ),
			'PT' => __( 'Portugal', 'wpconsent-premium' ),
			'PR' => __( 'Puerto Rico', 'wpconsent-premium' ),
			'QA' => __( 'Qatar', 'wpconsent-premium' ),
			'RE' => __( 'Reunion', 'wpconsent-premium' ),
			'RO' => __( 'Romania', 'wpconsent-premium' ),
			'RU' => __( 'Russia', 'wpconsent-premium' ),
			'RW' => __( 'Rwanda', 'wpconsent-premium' ),
			'BL' => __( 'Saint Barth&eacute;lemy', 'wpconsent-premium' ),
			'SH' => __( 'Saint Helena', 'wpconsent-premium' ),
			'KN' => __( 'Saint Kitts and Nevis', 'wpconsent-premium' ),
			'LC' => __( 'Saint Lucia', 'wpconsent-premium' ),
			'MF' => __( 'Saint Martin (French part)', 'wpconsent-premium' ),
			'SX' => __( 'Saint Martin (Dutch part)', 'wpconsent-premium' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'wpconsent-premium' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'wpconsent-premium' ),
			'SM' => __( 'San Marino', 'wpconsent-premium' ),
			'ST' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'wpconsent-premium' ),
			'SA' => __( 'Saudi Arabia', 'wpconsent-premium' ),
			'SN' => __( 'Senegal', 'wpconsent-premium' ),
			'RS' => __( 'Serbia', 'wpconsent-premium' ),
			'SC' => __( 'Seychelles', 'wpconsent-premium' ),
			'SL' => __( 'Sierra Leone', 'wpconsent-premium' ),
			'SG' => __( 'Singapore', 'wpconsent-premium' ),
			'SK' => __( 'Slovakia', 'wpconsent-premium' ),
			'SI' => __( 'Slovenia', 'wpconsent-premium' ),
			'SB' => __( 'Solomon Islands', 'wpconsent-premium' ),
			'SO' => __( 'Somalia', 'wpconsent-premium' ),
			'ZA' => __( 'South Africa', 'wpconsent-premium' ),
			'GS' => __( 'South Georgia/Sandwich Islands', 'wpconsent-premium' ),
			'KR' => __( 'South Korea', 'wpconsent-premium' ),
			'SS' => __( 'South Sudan', 'wpconsent-premium' ),
			'ES' => __( 'Spain', 'wpconsent-premium' ),
			'LK' => __( 'Sri Lanka', 'wpconsent-premium' ),
			'SD' => __( 'Sudan', 'wpconsent-premium' ),
			'SR' => __( 'Suriname', 'wpconsent-premium' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'wpconsent-premium' ),
			'SZ' => __( 'Eswatini', 'wpconsent-premium' ),
			'SE' => __( 'Sweden', 'wpconsent-premium' ),
			'CH' => __( 'Switzerland', 'wpconsent-premium' ),
			'SY' => __( 'Syria', 'wpconsent-premium' ),
			'TW' => __( 'Taiwan', 'wpconsent-premium' ),
			'TJ' => __( 'Tajikistan', 'wpconsent-premium' ),
			'TZ' => __( 'Tanzania', 'wpconsent-premium' ),
			'TH' => __( 'Thailand', 'wpconsent-premium' ),
			'TL' => __( 'Timor-Leste', 'wpconsent-premium' ),
			'TG' => __( 'Togo', 'wpconsent-premium' ),
			'TK' => __( 'Tokelau', 'wpconsent-premium' ),
			'TO' => __( 'Tonga', 'wpconsent-premium' ),
			'TT' => __( 'Trinidad and Tobago', 'wpconsent-premium' ),
			'TN' => __( 'Tunisia', 'wpconsent-premium' ),
			'TR' => __( 'Turkey', 'wpconsent-premium' ),
			'TM' => __( 'Turkmenistan', 'wpconsent-premium' ),
			'TC' => __( 'Turks and Caicos Islands', 'wpconsent-premium' ),
			'TV' => __( 'Tuvalu', 'wpconsent-premium' ),
			'UG' => __( 'Uganda', 'wpconsent-premium' ),
			'UA' => __( 'Ukraine', 'wpconsent-premium' ),
			'AE' => __( 'United Arab Emirates', 'wpconsent-premium' ),
			'GB' => __( 'United Kingdom (UK)', 'wpconsent-premium' ),
			'US' => __( 'United States (US)', 'wpconsent-premium' ),
			'UM' => __( 'United States (US) Minor Outlying Islands', 'wpconsent-premium' ),
			'UY' => __( 'Uruguay', 'wpconsent-premium' ),
			'UZ' => __( 'Uzbekistan', 'wpconsent-premium' ),
			'VU' => __( 'Vanuatu', 'wpconsent-premium' ),
			'VA' => __( 'Vatican', 'wpconsent-premium' ),
			'VE' => __( 'Venezuela', 'wpconsent-premium' ),
			'VN' => __( 'Vietnam', 'wpconsent-premium' ),
			'VG' => __( 'Virgin Islands (British)', 'wpconsent-premium' ),
			'VI' => __( 'Virgin Islands (US)', 'wpconsent-premium' ),
			'WF' => __( 'Wallis and Futuna', 'wpconsent-premium' ),
			'EH' => __( 'Western Sahara', 'wpconsent-premium' ),
			'WS' => __( 'Samoa', 'wpconsent-premium' ),
			'YE' => __( 'Yemen', 'wpconsent-premium' ),
			'ZM' => __( 'Zambia', 'wpconsent-premium' ),
			'ZW' => __( 'Zimbabwe', 'wpconsent-premium' ),
		);
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_endpoints() {
		if ( ! $this->enabled() ) {
			return;
		}

		register_rest_route(
			'wpconsent/v1',
			'/geolocation',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_location_info' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check permission.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool.
	 */
	public function check_permission( $request ) {
		return true;
	}

	/**
	 * Get location info.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response.
	 */
	public function get_location_info( $request ) {
		$ip       = wpconsent()->ip->get_client_ip();
		$ip       = wpconsent()->ip->anonymize_ip( $ip );
		$location = $this->get_location( $ip );

		return rest_ensure_response( $location );
	}

	/**
	 * Get location.
	 *
	 * @param string $ip The IP address.
	 *
	 * @return array.
	 */
	public function get_location( $ip ) {
		$location = array(
			'use_default' => true,
		);

		$response = wp_remote_get( $this->remote_api_url . $ip );

		if ( is_wp_error( $response ) ) {
			return $location;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return $location;
		}

		$data = json_decode( $body );

		// Get the current country and state (if available).
		$current_country = isset( $data->country_iso ) ? $data->country_iso : false;
		$current_state   = isset( $data->region_code ) ? $data->region_code : false;

		// Check if we should use the new geolocation groups format.
		$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

		if ( ! empty( $location_groups ) ) {
			// Use the new geolocation groups format.
			foreach ( $location_groups as $group ) {
				$match_found = false;

				// Check if the current location matches any location in the group.
				if ( ! empty( $group['locations'] ) ) {
					foreach ( $group['locations'] as $loc ) {
						// Check for country match.
						if ( 'country' === $loc['type'] && strtoupper( $current_country ) === strtoupper( $loc['code'] ) ) {
							$match_found = true;
							break;
						}

						// Check for US state match.
						if ( 'us_state' === $loc['type'] && 'US' === strtoupper( $current_country ) && strtoupper( $current_state ) === strtoupper( $loc['code'] ) ) {
							$match_found = true;
							break;
						}

						// Check for continent match.
						if ( 'continent' === $loc['type'] ) {
							$continents     = self::get_continents();
							$continent_code = strtoupper( $loc['code'] );
							if ( isset( $continents[ $continent_code ] ) && isset( $continents[ $continent_code ]['countries'] ) ) {
								// Convert current country to uppercase for case-insensitive comparison.
								$upper_country = strtoupper( $current_country );
								// Convert all continent countries to uppercase for case-insensitive comparison.
								$upper_continent_countries = array_map( 'strtoupper', $continents[ $continent_code ]['countries'] );
								if ( in_array( $upper_country, $upper_continent_countries, true ) ) {
									$match_found = true;
									break;
								}
							}
						}
					}
				}

				// If a match was found, apply the group's settings.
				if ( $match_found ) {
					$location['show_banner']             = isset( $group['show_banner'] ) ? (bool) $group['show_banner'] : true;
					$location['enable_script_blocking']  = isset( $group['enable_script_blocking'] ) ? (bool) $group['enable_script_blocking'] : true;
					$location['enable_consent_floating'] = isset( $group['enable_consent_floating'] ) ? (bool) $group['enable_consent_floating'] : true;
					$location['consent_mode']            = isset( $group['consent_mode'] ) ? $group['consent_mode'] : 'optin';
					$location['group_id']                = isset( $group['id'] ) ? $group['id'] : '';
					$location['country']                 = $current_country;
					$location['use_default']             = false;

					// Add button parameters if available.
					if ( isset( $group['accept_button_text'] ) ) {
						$location['accept_button_text'] = $group['accept_button_text'];
					}
					if ( isset( $group['cancel_button_text'] ) ) {
						$location['cancel_button_text'] = $group['cancel_button_text'];
					}
					if ( isset( $group['preferences_button_text'] ) ) {
						$location['preferences_button_text'] = $group['preferences_button_text'];
					}
					if ( isset( $group['accept_button_enabled'] ) ) {
						$location['accept_button_enabled'] = (bool) $group['accept_button_enabled'];
					}
					if ( isset( $group['cancel_button_enabled'] ) ) {
						$location['cancel_button_enabled'] = (bool) $group['cancel_button_enabled'];
					}
					if ( isset( $group['preferences_button_enabled'] ) ) {
						$location['preferences_button_enabled'] = (bool) $group['preferences_button_enabled'];
					}
					if ( isset( $group['button_order'] ) && is_array( $group['button_order'] ) ) {
						$location['button_order'] = $group['button_order'];
					}

					// Add banner message parameter if available.
					if ( isset( $group['banner_message'] ) && ! empty( $group['banner_message'] ) ) {
						$location['banner_message'] = $group['banner_message'];
					}

					// Once we find a matching group, we can stop checking.
					break;
				}
			}
		}

		return $location;
	}

	/**
	 * Get country code for an IP address.
	 *
	 * @param string $ip_address The IP address.
	 *
	 * @return string|null Country code or null on failure.
	 */
	public function get_location_for_ip( $ip_address ) {
		$response = wp_remote_get(
			$this->remote_api_url . $ip_address,
			array(
				'timeout' => 5,
			)
		);

		// Return null if there was an error or empty response.
		if ( is_wp_error( $response ) || empty( wp_remote_retrieve_body( $response ) ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		// Return country code if available, otherwise null.
		return ( isset( $data->country_iso ) && ! empty( $data->country_iso ) ) ? $data->country_iso : null;
	}

	/**
	 * Force enable the consent banner if a geolocation group is set to display it.
	 *
	 * @param bool $value The current value of the enable_consent_banner option.
	 *
	 * @return bool The filtered value.
	 */
	public function maybe_force_enable_banner( $value ) {
		// If banner is already enabled, return true.
		if ( true === boolval( $value ) ) {
			return true;
		}

		// If we are on the wpconsent-cookies page, skip. We don't need nonce verification here, we're just checking a string.
		if ( is_admin() && isset( $_GET['page'] ) && 'wpconsent-cookies' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $value;
		}

		// Check if we have any geolocation groups that are set to display the banner.
		$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

		if ( ! empty( $location_groups ) ) {
			foreach ( $location_groups as $group ) {
				// If any group is set to show the banner, return true.
				if ( isset( $group['show_banner'] ) && (bool) $group['show_banner'] ) {
					return true;
				}
			}
		}

		// If no geolocation group is set to display the banner, return the original value.
		return $value;
	}

	/**
	 * Force enable script blocking if a geolocation group is set to block scripts.
	 *
	 * @param bool $value The current value of the block_scripts option.
	 */
	public function maybe_force_enable_script_blocking( $value ) {
		// If we are on the wpconsent-cookies page, skip. We don't need nonce verification here, we're just checking a string.
		if ( is_admin() && isset( $_GET['page'] ) && 'wpconsent-cookies' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $value;
		}
		// Check if we have any geolocation groups that are set to display the banner.
		$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

		if ( ! empty( $location_groups ) ) {
			foreach ( $location_groups as $group ) {
				// If any group is set to show the banner, return true.
				if ( isset( $group['enable_script_blocking'] ) && (bool) $group['enable_script_blocking'] ) {
					return true;
				}
			}
		}

		return $value;
	}

	/**
	 * If we have a location that is optin we have to override the default allow on the frontend
	 * so that we don't use cookies in google consent mode before they are accepted.
	 *
	 * @param int $value The current value of the default allow option.
	 *
	 * @return int
	 */
	public function maybe_force_default_allow( $value ) {
		if ( is_admin() ) {
			return $value;
		}

		// Let's check if any of our geolocation groups are set to optin.
		$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

		if ( ! empty( $location_groups ) ) {
			foreach ( $location_groups as $group ) {
				// If any group is set to show the banner, return true.
				if ( isset( $group['consent_mode'] ) && 'optin' === $group['consent_mode'] ) {
					return 0; // Override to not allow cookies by default.
				}
			}
		}

		return $value;
	}

	/**
	 * Get predefined rule configuration.
	 *
	 * @param string $rule_type The rule type (gdpr, ccpa, lgpd).
	 *
	 * @return array|false The rule configuration or false if not found.
	 */
	public function get_predefined_rule_config( $rule_type ) {
		$rules = array(
			'gdpr' => array(
				'name'                    => __( 'GDPR Compliance', 'wpconsent-premium' ),
				'locations'               => array(
					'type' => 'continent',
					'code' => 'EU',
				),
				'enable_script_blocking'  => true,
				'show_banner'             => true,
				'enable_consent_floating' => true,
				'consent_mode'            => 'optin',
				'type_of_consent'         => 'GDPR',
			),
			'ccpa' => array(
				'name'                    => __( 'CCPA Compliance', 'wpconsent-premium' ),
				'locations'               => array(
					'type' => 'us_state',
					'code' => 'CA',
				),
				'enable_script_blocking'  => true,
				'show_banner'             => true,
				'enable_consent_floating' => true,
				'consent_mode'            => 'optout',
				'type_of_consent'         => 'CCPA',
			),
			'lgpd' => array(
				'name'                    => __( 'LGPD Compliance', 'wpconsent-premium' ),
				'locations'               => array(
					'type' => 'country',
					'code' => 'BR',
				),
				'enable_script_blocking'  => true,
				'show_banner'             => true,
				'enable_consent_floating' => true,
				'consent_mode'            => 'optin',
				'type_of_consent'         => 'LGPD',
			),
		);

		return isset( $rules[ $rule_type ] ) ? $rules[ $rule_type ] : false;
	}

	/**
	 * Filters and modifies JavaScript data for the frontend based on geolocation groups and consent settings.
	 *
	 * @param array $js_data The existing JavaScript data passed for modification.
	 *
	 * @return array Modified JavaScript data including geolocation-based consent settings if applicable.
	 */
	public function frontend_js_data( $js_data ) {

		// Grab the settings unfiltered.
		$options = wpconsent()->settings->get_options();

		$js_data['original_default_allow']              = isset( $options['default_allow'] ) && boolval( $options['default_allow'] );
		$js_data['original_enable_consent_banner']      = boolval( $options['enable_consent_banner'] );
		$js_data['original_enable_script_blocking']     = boolval( $options['enable_script_blocking'] );
		$js_data['original_accept_button_enabled']      = boolval( $options['accept_button_enabled'] );
		$js_data['original_cancel_button_enabled']      = boolval( $options['cancel_button_enabled'] );
		$js_data['original_preferences_button_enabled'] = boolval( $options['preferences_button_enabled'] );
		$js_data['original_banner_message']             = $options['banner_message'] ?? '';

		return $js_data;
	}

	/**
	 * Checks if geolocation cookie needs to be added and adds it if necessary.
	 * This method should be called whenever location groups are created or updated.
	 *
	 * @return void
	 */
	public function maybe_add_geolocation_cookie() {
		// Get location groups
		$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

		// Only proceed if we have at least one location group.
		if ( ! empty( $location_groups ) ) {
			// Get the categories to find the essential category ID.
			$categories = wpconsent()->cookies->get_categories();

			// Get cookies in the essential category to check if geolocation cookie exists.
			$essential_cookies = wpconsent()->cookies->get_cookies_by_category( $categories['essential']['id'] );

			// Check if the geolocation cookie already exists.
			$geolocation_cookie_exists = false;
			foreach ( $essential_cookies as $cookie ) {
				if ( 'wpconsent_geolocation' === $cookie['cookie_id'] ) {
					$geolocation_cookie_exists = true;
					break;
				}
			}

			// If the geolocation cookie doesn't exist, add it.
			if ( ! $geolocation_cookie_exists ) {
				wpconsent()->cookies->add_cookie(
					'wpconsent_geolocation',
					__( 'Geolocation Config', 'wpconsent-premium' ),
					__( 'This cookie is used to store the consent settings based on the visitor\'s location.', 'wpconsent-premium' ),
					'essential',
					'30 days'
				);
			}
		}
	}
}
