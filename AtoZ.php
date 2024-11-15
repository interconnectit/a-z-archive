<?php
/*
Plugin Name: A-Z Archive - Alphabetic sort and filtering
Plugin URI: http://interconnectit.com
Description: Adds the option to change the sort order of a post type to alphabetic along with the option of filtering posts to initial letter. Just add add_post_type_support({post_type}, 'alpha_sort' );
Version: 1.0.1
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

    protected const DOM       = 'icit';
    protected const SUPPORT   = 'alpha_sort';
    protected const QUERY_VAR = 'alpha_filter';

    public function __construct() {
        add_filter( 'pre_get_posts', [ $this, 'set_query_var' ], 8, 1 );
        add_filter( 'posts_where', [ $this, 'filter' ], 100, 2 );
        add_filter( 'query_vars', [ $this, 'add_alpha_var' ] );
//        add_filter( 'post_rewrite_rules', [ $this, 'rewrite_rules' ], 1 );

        add_filter( 'disable_months_dropdown', [ $this, 'disable_months_dropdown' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_posts' ], 10, 2 );
    }

    /**
     * @param array $vars
     *
     * @return array
     */
    public function add_alpha_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * @param bool   $disable
     * @param string $post_type
     *
     * @return bool
     */
    public function disable_months_dropdown( bool $disable, string $post_type ): bool {
        if ( $this->post_type_supports( $post_type ) ) {
            return true;
        }

        return $disable;
    }

    /**
     * @param string $post_type
     *
     * @return void
     */
    public function restrict_manage_posts( string $post_type ): void {
        if ( !$this->post_type_supports( $post_type ) ) {
            return;
        }

        $current = get_query_var( self::QUERY_VAR ) ? : false;

        echo '<label class="screen-reader-text" for="filter-by-alpha">' . __( 'Filter by initial letter', self::DOM ) . '</label>';
        echo '<select name="' . self::QUERY_VAR . '" id="filter-by-alpha">';
        echo '<option value="">' . esc_html( __( 'All', self::DOM ) ) . '</option>';
        echo '<option value="9"' . selected( $current, '9', false ) . '>' . esc_html( __( '#', self::DOM ) ) . '</option>';
        foreach ( range( 'a', 'z' ) as $alpha ) {
            echo '<option value="' . esc_attr( $alpha ) . '"' . selected( $current, $alpha, false ) . '>' . strtoupper( esc_html( $alpha ) ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * @param string|string[] $post_type
     *
     * @return bool
     */
    protected function post_type_supports( string|array $post_type ): bool {
        if ( is_array( $post_type ) ) {
            // Return true if all post types support alpha_sort
            return count( array_filter( $post_type, [ $this, 'post_type_supports' ] ) ) === count( $post_type );
        }

        return post_type_supports( $post_type, self::SUPPORT );
    }

    /**
     * If the post_type supports alpha_sort we'll flag that in the query.
     *
     * @param WP_Query $query
     *
     * @return WP_Query
     */
    public function set_query_var( WP_Query $query ): WP_Query {
        if ( !$query->is_main_query() || $query->is_search() ) {
            return $query;
        }

        if ( empty( $query->query_vars['post_type'] ) || is_array( $query->query_vars['post_type'] ) || !$this->post_type_supports( $query->query_vars['post_type'] ) ) {
            return $query;
        }

        // Check if we're going to filter to a single letter. e.g. alpha=a
        if ( isset( $query->query_vars[self::QUERY_VAR] ) ) {
            $alpha = strtolower( esc_sql( substr( $query->get( self::QUERY_VAR ), 0, 1 ) ) );
            $alpha = $alpha === '9' || preg_match( '/^[^a-z]$/', $alpha ) ?
                '9' : substr( $alpha, 0, 1 );

            $query->set( self::QUERY_VAR, $alpha );

            // Disable ElasticPress integration for this query
            $query->set( 'ep_integrate', false );
        }

        if ( is_admin() && !empty( $query->get( 'orderby' ) ) ) {
            return $query;
        }

        // We're going to sort alphabetically
        $query->set( 'orderby', 'title' );
        $query->set( 'order', 'ASC' );

        return $query;
    }

    /**
     * @param string   $where
     * @param WP_Query $wp_query
     *
     * @return string
     */
    public function filter( string $where, WP_Query $wp_query ): string {
        global $wpdb;

        $filter = $wp_query->get( self::QUERY_VAR );

        if ( empty( $filter ) || !$this->post_type_supports( $wp_query->get( 'post_type' ) ) ) {
            return $where;
        }

        $where_add = '';

        if ( $filter === '9' ) {
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
        $links['current'] = get_query_var( self::QUERY_VAR );
        $links['all'] = $root;
        $links['#'] = add_query_arg( [ self::QUERY_VAR => '9' ], $root );
        foreach ( range( 'a', 'z' ) as $alpha ) {
            $links[$alpha] = add_query_arg( [ self::QUERY_VAR => $alpha ], $root );
        }

        return $links;
    }

    /**
     * @param string[] $post_rewrite
     *
     * @return string[]
     */
    public function rewrite_rules( array $post_rewrite = [] ): array {
        add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)', self::QUERY_VAR . '=' );

        foreach ( get_post_types( [ 'public' => true ] ) as $post_type ) {
            if ( !post_type_supports( $post_type, self::SUPPORT ) ) {
                continue;
            }

            // Get the post_type object
            $post_type = get_post_type_object( $post_type );

            // Do we support rewrites?
            if ( empty( $post_type->rewrite ) || empty( $post_type->has_archive ) ) {
                continue;
            }

            $slug = $post_type->has_archive === true ? $post_type->name : $post_type->has_archive;
            add_permastruct(
                $slug . '_' . self::QUERY_VAR,
                '/' . $slug . '/alpha/%' . self::QUERY_VAR . '%',
                false
            );
        }

        return $post_rewrite;
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
                '', 'all' => false,
                '#'       => $current === '9',
                default   => $current === $title,
            };

            $output .= sprintf( '<li%3$s><a href="%2$s">%1$s</a></li>',
                esc_html( $title ),
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
