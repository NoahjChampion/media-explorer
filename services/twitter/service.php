<?php
/*
Copyright © 2013 Code for the People Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

namespace EMM\Services\Twitter;

defined( 'ABSPATH' ) or die();

class Template extends \EMM\Template {

	public function item( $id ) {
		?>
		<div id="emm-item-{{ data.id }}" class="emm-item-area clearfix" data-id="{{ data.id }}">
			<div class="emm-item-thumb">
				<img src="{{ data.thumbnail }}">
			</div>
			<div class="emm-item-main">
				<div class="emm-item-author">
					<span class="emm-item-author-name">{{ data.meta.user.name }}</span>
					<span class="emm-item-author-screen-name">@{{ data.meta.user.screen_name }}</span>
				</div>
				<div class="emm-item-content">
					{{{ data.content }}}
				</div>
				<div class="emm-item-date">
					{{ data.date }}
				</div>
			</div>
		</div>
		<a href="#" id="emm-check-{{ data.id }}" data-id="{{ data.id }}" class="check" title="<?php esc_attr_e( 'Deselect', 'emm' ); ?>">
			<div class="media-modal-icon"></div>
		</a>
		<?php
	}

	public function thumbnail( $id ) {
		?>
		<?php
	}

	public function search( $id ) {
		?>
		<input
			type="search"
			name="q"
			value="{{ data.params.q }}"
			class="emm-input-text emm-input-search"
			size="30"
			placeholder="<?php esc_attr_e( 'Search Twitter', 'emm' ); ?>"
		>
		<div class="spinner"></div>
		<?php
	}

	public function first_time( $id ) {
		?>
		<p>Welcome!</p>
		<?php
	}

}

class Service extends \EMM\Service {

	public $credentials = null;

	public function __construct() {

		# Go!
		$this->set_template( new Template );

	}

	public function request( array $request ) {

		if ( is_wp_error( $connection = $this->get_connection() ) )
			return $connection;

		# when we introduce other fields we'll build $q here
		# operators: https://dev.twitter.com/docs/using-search
		$q = $request['params']['q'];

		$args = array(
			'q'           => trim( $q ),
			'result_type' => 'recent',
			'count'       => 20,
		);

		if ( !empty( $request['since'] ) )
			$args['since_id'] = $request['since'];
		if ( !empty( $request['before'] ) )
			$args['max_id'] = $request['before'];

		$response = $connection->get( sprintf( '%s/search/tweets.json', untrailingslashit( $connection->host ) ), $args );

		# @TODO switch the twitter oauth class over to wp http api:
		if ( 200 == $connection->http_code ) {

			return $this->response( $response );

		} else {

			return new \WP_Error(
				'emm_twitter_failed_request',
				sprintf( __( 'Could not connect to Twitter (error %s).', 'emm' ),
					esc_html( $connection->http_code )
				)
			);

		}

	}

	public function status_url( $status ) {

		return sprintf( 'https://twitter.com/%s/status/%s',
			$status->user->screen_name,
			$status->id_str
		);

	}

	public function status_content( $status ) {

		$text = $status->text;

		# @TODO more processing (hashtags, @s etc)
		$text = make_clickable( $text );
		$text = str_replace( ' href="', ' target="_blank" href="', $text );

		return $text;

	}

	public function response( $r ) {

		if ( !isset( $r->statuses ) or empty( $r->statuses ) )
			return false;

		$response = new \EMM\Response;

		foreach ( $r->statuses as $status ) {

			$item = new \EMM\Response_Item;

			$item->set_id( $status->id_str );
			$item->set_url( self::status_url( $status ) );
			$item->set_content( self::status_content( $status ) );
			$item->set_thumbnail( is_ssl() ? $status->user->profile_image_url_https : $status->user->profile_image_url );
			$item->set_date( strtotime( $status->created_at ) );
			$item->set_date_format( 'jS F Y' );

			$item->add_meta( 'user', array(
				'name'        => $status->user->name,
				'screen_name' => $status->user->screen_name,
			) );

			$response->add_item( $item );

		}

		return $response;

	}

	public function requires() {
		return array(
			'oauth' => '\OAuthConsumer'
		);
	}

	public function labels() {
		return array(
			'title'     => sprintf( __( 'Insert from %s', 'emm' ), 'Twitter' ),
			# @TODO the 'insert' button text gets reset when selecting items. find out why.
			'insert'    => __( 'Insert Tweet', 'emm' ),
			'noresults' => __( 'No tweets matched your search query', 'emm' ),
		);
	}

	private function get_connection() {

		$credentials = $this->get_credentials();

		# Despite saying that application-only authentication for search would be available by the
		# end of March 2013, Twitter has still not implemented it. This means that for API v1.1 we
		# still need user-level authentication in addition to application-level authentication.
		#
		# If the time comes that application-only authentication is made available for search, the
		# use of the oauth_token and oauth_token_secret fields below can simply be removed.
		#
		# Further bedtime reading:
		#
		# https://dev.twitter.com/discussions/11079
		# https://dev.twitter.com/discussions/13210
		# https://dev.twitter.com/discussions/14016
		# https://dev.twitter.com/discussions/15744

		foreach ( array( 'consumer_key', 'consumer_secret', 'oauth_token', 'oauth_token_secret' ) as $field ) {
			if ( !isset( $credentials[$field] ) or empty( $credentials[$field] ) ) {
				return new \WP_Error(
					'emm_twitter_no_connection',
					__( 'oAuth connection to Twitter not found.', 'emm' )
				);
			}
		}

		if ( !class_exists( 'WP_Twitter_OAuth' ) )
			require_once dirname( __FILE__ ) . '/class.wp-twitter-oauth.php';

		$connection = new WP_Twitter_OAuth(
			$credentials['consumer_key'],
			$credentials['consumer_secret'],
			$credentials['oauth_token'],
			$credentials['oauth_token_secret']
		);

		$connection->useragent = sprintf( 'Extended Media Manager at %s', home_url() );

		return $connection;

	}

	private function get_credentials() {

		if ( is_null( $this->credentials ) )
			$this->credentials = (array) apply_filters( 'emm_twitter_credentials', array() );

		return $this->credentials;

	}

}

add_filter( 'emm_services', function( $services ) {
	$services['twitter'] = new Service;
	return $services;
} );