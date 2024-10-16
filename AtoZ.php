<?php
/*
Plugin Name: A-Z Archive - Alphabetic sort and filtering
Plugin URI: http://interconnectit.com
Description: Adds the option to change the sort order of a post type to alphabetic along with the option of filtering posts to initial letter. Just add add_post_type_support({post_type}, 'alpha_sort' );
Version: 1.0
Author: James Whitehead
Author URI: http://interconnectit.com
License: GPL2
*/

declare( strict_types = 1 );

namespace ICIT\StandFirst\PostSort;

use WP_Query;

add_action( 'after_setup_theme', [ 'ICIT\StandFirst\PostSort\AtoZ', 'instance' ] );

/**
 * @package ICIT\StandFirst\PostSort\AtoZ
 */
class AtoZ {
    /**
     * @var self|null
     */
    protected static ?self $instance = null;

    protected const DOM     = 'icit';
    protected const SUPPORT = 'alpha_sort';

    public function __construct() {
        add_filter( 'pre_get_posts', [ $this, 'set_query_var' ], 8, 1 );
        add_filter( 'posts_where', [ $this, 'filter' ], 100, 2 );
        add_filter( 'posts_orderby', [ $this, 'change_order' ], 10, 2 );
        add_filter( 'query_vars', [ $this, 'add_alpha_var' ] );
    }

    /**
     * @param array $vars
     *
     * @return array
     */
    public function add_alpha_var( array $vars ): array {
        $vars[] = 'alpha_filter';

        return $vars;
    }

    /**
     * @param string $post_type
     *
     * @return bool
     */
    protected function post_type_supports( string $post_type ): bool {
        return post_type_supports( $post_type, self::SUPPORT );
    }

    /**
     * If the post_type supports alpha_sort we'll flag that in the query.
     *
     * @param WP_Query $query
     */
    public function set_query_var( WP_Query $query ): void {
        if ( !$query->is_main_query() || $query->is_search() || is_admin() ) {
            return;
        }

        if ( empty( $query->query_vars['post_type'] ) || is_array( $query->query_vars['post_type'] ) || !$this->post_type_supports( $query->query_vars['post_type'] ) ) {
            return;
        }

        // We're going to sort alphabetically
        $query->set( 'alpha_sort', true );

        // Check if we're going to filter to a single letter. e.g. alpha=a
        if ( isset( $query->query_vars['alpha_filter'] ) ) {
            $alpha = strtolower( $query->get( 'alpha_filter' ) );
            $alpha = $alpha === 'sym' || preg_match( '/^[^a-z]$/', substr( $alpha, 0, 1 ) ) ?
                'sym' : substr( $alpha, 0, 1 );

            $query->set( 'alpha_filter', $alpha );
        }
    }

    /**
     * Change the sort order to order by post_title.
     *
     * @param string   $order_by
     * @param WP_Query $wp_query
     *
     * @return string
     */
    public function change_order( string $order_by, WP_Query $wp_query ): string {
        if ( !empty( $wp_query->query_vars['alpha_sort'] ) ) {
            $order_by = 'wp_posts.post_title ASC, wp_posts.menu_order ASC, wp_posts.ID ';
        }

        return $order_by;
    }

    /**
     * @param string   $where
     * @param WP_Query $wp_query
     *
     * @return string
     */
    public function filter( string $where, WP_Query $wp_query ): string {
        global $wpdb;

        $sort = $wp_query->get( 'alpha_sort' );
        $filter = $wp_query->get( 'alpha_filter' );

        if ( empty( $sort ) || empty( $filter ) ) {
            return $where;
        }

        $where_add = '';

        if ( $filter === 'sym' ) {
            $where_add = " AND LOWER( SUBSTRING( $wpdb->posts.post_title, 1, 1 ) ) NOT IN ('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z') ";
        }
        elseif ( preg_match( '/^[a-z]$/', $filter ) ) {
            $where_add = $wpdb->prepare( " AND LOWER( SUBSTRING( $wpdb->posts.post_title, 1, 1 ) ) = %s ", $filter );
        }

        return $where_add . $where;
    }

    /**
     * @param string $post_type The post_type string
     *
     * @return array|null
     */
    public static function get_post_type_alpha_filters( string $post_type ): ?array {
        // Unsupported post type
        if ( !self::instance()->post_type_supports( $post_type ) ) {
            return null;
        }

        // Get the post type root.
        $root = get_post_type_archive_link( $post_type );

        // Build an array of links to each filter
        $links = [];
        $links['current'] = get_query_var( 'alpha_filter' );
        $links['all'] = $root;
        $links['#'] = add_query_arg( [ 'alpha_filter' => 'sym' ], $root );
        foreach ( range( 'a', 'z' ) as $alpha ) {
            $links[$alpha] = add_query_arg( [ 'alpha_filter' => $alpha ], $root );
        }

        return $links;
    }

    /**
     * @param string $post_type
     * @param array  $args
     *
     * @return string|null
     * @noinspection PhpUnused
     * @noinspection HtmlUnknownTarget
     */
    public static function post_type_alpha_filters( string $post_type, array $args = [] ): ?string {
        $filters = self::get_post_type_alpha_filters( $post_type );
        if ( empty( $filters ) ) {
            return null;
        }

        $defaults = [
            'title'     => __( '', self::DOM ),
            'all_title' => __( 'All', self::DOM ),
        ];
        $args = wp_parse_args( $args, $defaults );

        // Get the current from the array then remove it.
        $current = !empty( $filters['current'] ) ? $filters['current'] : false;
        unset( $filters['current'] );

        $output = '<ul class="alpha-filter">';
        if ( !empty( $args['title'] ) ) {
            $output .= sprintf( '<li class="title">%s</li>', esc_html( $args['title'] ) );
        }

        foreach ( $filters as $title => $link ) {
            $is_current = match ( $title ) {
                '', 'all' => empty( $current ),
                '#'       => $current === 'sym',
                default   => $current === $title,
            };

            $output .= sprintf( '<li%3$s><a href="%2$s">%1$s</a></li>',
                esc_html( empty( $current ) ? $args['all_title'] : $title ),
                esc_attr( $link ),
                $is_current ? ' class="current-item"' : ''
            );
        }
        $output .= '</ul>';

        return $output;
    }

    /**
     * Create and return an instance of this class
     *
     * @return self
     */
    public static function instance(): self {
        null === self::$instance && self::$instance = new self();

        return self::$instance;
    }
}