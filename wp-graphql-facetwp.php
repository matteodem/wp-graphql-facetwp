<?php
/**
 * Plugin Name: WP GraphQL FacetWP
 * Plugin URI: https://github.com/hsimah/wp-graphql-facetwp
 * Description: WP GraphQL provider for FacetWP
 * Author: hsimah
 * Author URI: http://www.hsimah.com
 * Version: 0.0.1
 * Text Domain: wpgraphql-facetwp
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  WPGraphQL_FacetWP
 * @author   hsimah
 * @version  0.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Exit if WPGraphQL does not exist
if ( ! class_exists( 'WPGraphQL' ) ) {
    exit;
}

// Exit if FacetWP does not exist
if ( ! class_exists( 'FacetWP' ) ) {
    exit;
}

if ( ! class_exists( 'WPGraphQL_FacetWP' ) ) :
    final class WPGraphQL_FacetWP {
        
        /**
		 * Stores the instance of the WPGraphQL_FacetWP class
		 *
		 * @var WPGraphQL_FacetWP The one true WPGraphQL_FacetWP
		 * @since  0.0.1
		 * @access private
		 */
        private static $instance;
        
		/**
		 * The instance of the WPGraphQL_FacetWP object
		 *
		 * @return object|WPGraphQL_FacetWP - The one true WPGraphQL_FacetWP
		 * @since  0.0.1
		 * @access public
		 */
        public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPGraphQL_FacetWP ) ) {
                self::$instance = new WPGraphQL_FacetWP();
                self::$instance->init();
			}

			/**
			 * Return the WPGraphQL_FacetWP Instance
			 */
			return self::$instance;
        }
        
		/**
		 * Throw error on object clone.
		 * The whole idea of the singleton design pattern is that there is a single object
		 * therefore, we don't want the object to be cloned.
		 *
		 * @since  0.0.1
		 * @access public
		 * @return void
		 */
        public function __clone() {

			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'The WPGraphQL_FacetWP class should not be cloned.', 'wpgraphql-facetwp' ), '0.0.1' );

		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since  0.0.1
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {

			// De-serializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the WPGraphQL_FacetWP class is not allowed', 'wpgraphql-facetwp' ), '0.0.1' );

        }

        /**
		 * Register WPGraphQL Facet query.
		 *
		 * @access public
		 * @since  0.0.1
		 * @return void
		 */
		public static function register( $type ) {
            
            $post_type = get_post_type_object( $type );

            if ( ! $post_type->show_in_graphql ) return;
            
            $config = [
                'type'                  => $type,
                'graphql_type'          => $post_type->graphql_single_name,
                'graphql_plural_type'   => $post_type->graphql_plural_name,
            ];

            self::$instance->register_root_field( $config );
            self::$instance->register_input_arg_types( $config );
            self::$instance->register_output_types( $config );
            self::$instance->register_facet_connection( $config );

        }
        
        /**
		 * Initialise plugin.
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function init() {

        }

        /**
		 * Register facet-type root field.
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function register_root_field( $config ) {

            $type = $config['type'];
            $graphql_type = $config['graphql_type'];
            $field_name = $graphql_type . 'Facet';

            register_graphql_field( 'RootQuery', $field_name, [
                'type'        => $field_name,
                'description' => __( $graphql_type . ' FacetWP Query', 'wpgraphql-facetwp' ),
                'args'        => [
                    'where' => [
                        'type'          => $field_name . 'WhereArgs',
                        'description'   => __( 'Arguments for ' . $field_name . ' query', 'wpgraphql-facetwp' ),
                    ],
                ],
                'resolve'     => function ( $source, array $args ) {    
    
                    $where = $args['where'];
    
                    $fwp_args = [
                        'facets'        => $where['query'],
                        'query_args'    => [
                            'post_type'         => $type,
                            'post_status'       => $where['status'],
                            'posts_per_page'    => 10, // TODO pagination somehow
                            'paged'             => 1,
                        ],
                    ];
    
                    $fwp = new FacetWP_API_Fetch();
                    $payload = $fwp->process_request( $fwp_args );
    
                    /**
                     * facets array is the resolved payload for this field
                     * results & pager are returned so the connection resoler can use the data
                     */
                    return [
                        'facets' => array_values( $payload['facets'] ),
                        'results' => $payload['results'],
                        'pager' => $payload['pager'],
                    ];
                },
            ] );

        }

        /**
		 * Register facet-type connection types.
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function register_facet_connection( $config ) {

            $type = $config['type'];
            $graphql_type = $config['graphql_type'];
            $field_name = $graphql_type . 'Facet';
            $graphql_plural_type = $config['graphql_plural_type'];

            register_graphql_connection( [
                'fromType'         => $field_name,
                'toType'           => $graphql_type,
                'fromFieldName'    => lcfirst( $graphql_plural_type ),
                'resolve'          => function ( $source, $args, $context, $info ) {
                    return [];
                },
            ] );

        }

        /**
		 * Register output types.
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function register_output_types( $config ) {

            $type = $config['type'];
            $graphql_type = $config['graphql_type'];
            $field_name = $graphql_type . 'Facet';

            register_graphql_object_type( $field_name, [
                'description' => __( $graphql_type . ' FacetWP Payload', 'wpgraphql-facetwp' ),
                'fields' => [
                    'facets' => [
                        'type' => [
                            'list_of' => 'Facet',
                        ],
                    ],
                ],
            ] );
    
            register_graphql_object_type( 'Facet', [
                'description' => __( 'Active FacetWP payload', 'wpgraphql-facetwp' ),
                'fields' => [
                    'name' => [
                        'type' => 'String',
                        'description' => __( 'Facet name', 'wpgraphql-facetwp' ),
                    ],
                    'label' => [
                        'type' => 'String',
                        'description' => __( 'Facet label', 'wpgraphql-facetwp' ),
                    ],
                    'type' => [
                        'type' => 'String',
                        'description' => __( 'Facet type', 'wpgraphql-facetwp' ),
                    ],
                    'selected' => [
                        'type' => [
                            'list_of' => 'String',
                        ],
                        'description' => __( 'Selected values', 'wpgraphql-facetwp' ),
                    ],
                    'choices' => [
                        'type' => [
                            'list_of' => 'FacetChoice',
                        ],
                        'description' => __( 'Facet choices', 'wpgraphql-facetwp' ),
                    ],
                ],
            ]);
    
            register_graphql_object_type( 'FacetChoice', [
                'description' => __( 'FacetWP choice', 'wpgraphql-facetwp' ),
                'fields' => [
                    'value' => [
                        'type' => 'String',
                        'description' => __( 'Taxonomy value or post ID', 'wpgraphql-facetwp' ),
                    ],
                    'label' => [
                        'type' => 'String',
                        'description' => __( 'Taxonomy label or post title', 'wpgraphql-facetwp' ),
                    ],
                    'count' => [
                        'type' => 'Int',
                        'description' => __( 'Count', 'wpgraphql-facetwp' ),
                    ],
                    'depth' => [
                        'type' => 'Int',
                        'description' => __( 'Depth', 'wpgraphql-facetwp' ),
                    ],
                    'termId' => [
                        'type' => 'Int',
                        'description' => __( 'Term ID (Taxonomy choices only)', 'wpgraphql-facetwp' ),
                    ],
                    'parentId' => [
                        'type' => 'Int',
                        'description' => __( 'Parent Term ID (Taxonomy choices only', 'wpgraphql-facetwp' ),
                    ],
                ],
            ] );

        }

        /**
		 * Register input argument types.
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function register_input_arg_types( $config ) {
            
            $facets = FWP()->helper->get_facets();
    
            $type = $config['type'];
            $graphql_type = $config['graphql_type'];
            $field_name = $graphql_type . 'Facet';

            register_graphql_input_type( 'FacetQueryArgs', [
                'description' => __( 'Seleted facets for ' . $field_name . ' query', 'wpgraphql-facetwp' ),
                'fields'      => array_reduce( $facets, function( $prev, $cur ) {
                    if ( $cur && $cur['name'] ) {
                        // list_of String: checkbox, fselect && multuple
                        // String: radio
                        // TODO handle other facet types
    
                        $type = [
                            'list_of' => 'String'
                        ];
    
                        switch ( $cur['type'] ) {
                            case 'checkboxes':
                                break;
                            case 'fselect':
                                if ( $cur['multiple'] === 'yes' ) break;
                            case 'radio':
                                $type = 'String';
                                break;
                        }
    
                        $prev[$cur['name']] = [
                            'type' => $type,
                            'description' => __( $cur['label'] . ' facet query', 'wpgraphql-facetwp' ),
                        ];
                    }
        
                    return $prev;
                }, [] ),
            ] );
    
            register_graphql_input_type( $field_name . 'WhereArgs', [
                'description' => __( 'Arguments for ' . $field_name . ' query', 'wpgraphql-facetwp' ),
                'fields'      => [
                    // TODO pass orderby to facet query
                    'orderby'      => [
                        'type'        => [
                            'list_of' => 'PostObjectsConnectionOrderbyInput',
                        ],
                        'description' => __( 'What paramater to use to order the objects by.', 'wpgraphql-facetwp' ),
                    ],
                    'status' => [
                        'type' => 'PostStatusEnum',
                    ],
                    'query' => [
                        'type' => 'FacetQueryArgs',
                    ],
                ],
            ] );
        }
    }

endif;

add_action( 'init', 'wpgraphql_facetwp_init' );

if ( ! function_exists( 'wpgraphql_facetwp_init' ) ) {
	/**
	 * Function that instantiates the plugins main class
	 *
	 * @since 0.0.1
	 */
	function wpgraphql_facetwp_init() {

		/**
		 * Return an instance of the action
		 */
		return \WPGraphQL_FacetWP::instance();
	}
}

/**
 * Refactor below to external class TODO
 */

/**
 * Register a post type as a FacetWP queryable
 *
 * @param string $type_name The name of the Type to register
 */
function register_graphql_facet_type( $type_name ) {
	\WPGraphQL_FacetWP::register( $type_name );
}