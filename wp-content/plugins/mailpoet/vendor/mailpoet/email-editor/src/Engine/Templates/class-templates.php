<?php
declare(strict_types = 1);
namespace MailPoet\EmailEditor\Engine\Templates;
if (!defined('ABSPATH')) exit;
use MailPoet\EmailEditor\Engine\Email_Styles_Schema;
use WP_Block_Template;
class Templates {
 const MAILPOET_EMAIL_META_THEME_TYPE = 'mailpoet_email_theme';
 const MAILPOET_TEMPLATE_EMPTY_THEME = array( 'version' => 2 ); // The version 2 is important to merge themes correctly.
 private Utils $utils;
 private string $plugin_slug = 'mailpoet/mailpoet';
 private string $post_type = 'mailpoet_email';
 private string $template_directory;
 private array $templates = array();
 private array $theme_json = array();
 public function __construct(
 Utils $utils
 ) {
 $this->utils = $utils;
 $this->template_directory = __DIR__ . DIRECTORY_SEPARATOR;
 }
 public function initialize(): void {
 add_filter( 'pre_get_block_file_template', array( $this, 'get_block_file_template' ), 10, 3 );
 add_filter( 'get_block_templates', array( $this, 'add_block_templates' ), 10, 3 );
 add_filter( 'theme_templates', array( $this, 'add_theme_templates' ), 10, 4 ); // Needed when saving post – template association.
 add_filter( 'get_block_template', array( $this, 'add_block_template_details' ), 10, 1 );
 add_filter( 'rest_pre_insert_wp_template', array( $this, 'force_post_content' ), 9, 1 );
 $this->initialize_templates();
 $this->initialize_api();
 }
 public function get_block_template( $template_id ) {
 $templates = $this->get_block_templates();
 return $templates[ $template_id ] ?? null;
 }
 public function get_block_template_theme( $template_id, $template_wp_id = null ) {
 // First check if there is a user updated theme saved.
 $theme = $this->get_custom_template_theme( $template_wp_id );
 if ( $theme ) {
 return $theme;
 }
 // If there is no user edited theme, look for default template themes in files.
 ['prefix' => $template_prefix, 'slug' => $template_slug] = $this->utils->get_template_id_parts( $template_id );
 if ( $this->plugin_slug !== $template_prefix ) {
 return self::MAILPOET_TEMPLATE_EMPTY_THEME;
 }
 if ( ! isset( $this->theme_json[ $template_slug ] ) ) {
 $json_file = $this->template_directory . $template_slug . '.json';
 if ( file_exists( $json_file ) ) {
 $this->theme_json[ $template_slug ] = json_decode( (string) file_get_contents( $json_file ), true );
 }
 }
 return $this->theme_json[ $template_slug ] ?? self::MAILPOET_TEMPLATE_EMPTY_THEME;
 }
 public function get_block_file_template( $result, $template_id, $template_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 ['prefix' => $template_prefix, 'slug' => $template_slug] = $this->utils->get_template_id_parts( $template_id );
 if ( $this->plugin_slug !== $template_prefix ) {
 return $result;
 }
 $template_path = $template_slug . '.html';
 if ( ! is_readable( $this->template_directory . $template_path ) ) {
 return $result;
 }
 return $this->get_block_template_from_file( $template_path );
 }
 public function add_block_templates( $query_result, $query, $template_type ) {
 if ( 'wp_template' !== $template_type ) {
 return $query_result;
 }
 $post_type = isset( $query['post_type'] ) ? $query['post_type'] : '';
 if ( $post_type && $post_type !== $this->post_type ) {
 return $query_result;
 }
 foreach ( $this->get_block_templates() as $block_template ) {
 $fits_slug_query = ! isset( $query['slug__in'] ) || in_array( $block_template->slug, $query['slug__in'], true );
 $fits_area_query = ! isset( $query['area'] ) || ( property_exists( $block_template, 'area' ) && $block_template->area === $query['area'] );
 $should_include = $fits_slug_query && $fits_area_query;
 if ( $should_include ) {
 $query_result[] = $block_template;
 }
 }
 return $query_result;
 }
 public function add_theme_templates( $templates, $theme, $post, $post_type ) {
 if ( $post_type && $post_type !== $this->post_type ) {
 return $templates;
 }
 foreach ( $this->get_block_templates() as $block_template ) {
 $templates[ $block_template->slug ] = $block_template;
 }
 return $templates;
 }
 public function force_post_content( $changes ) {
 if ( empty( $changes->post_content ) && ! empty( $changes->ID ) ) {
 // Find the existing post object.
 $post = get_post( $changes->ID );
 if ( $post && ! empty( $post->post_content ) ) {
 $changes->post_content = $post->post_content;
 }
 }
 return $changes;
 }
 public function add_block_template_details( $block_template ) {
 if ( ! $block_template || ! isset( $this->templates[ $block_template->slug ] ) ) {
 return $block_template;
 }
 if ( empty( $block_template->title ) ) {
 $block_template->title = $this->templates[ $block_template->slug ]['title'];
 }
 if ( empty( $block_template->description ) ) {
 $block_template->description = $this->templates[ $block_template->slug ]['description'];
 }
 return $block_template;
 }
 private function initialize_templates(): void {
 $this->templates['email-general'] = array(
 'title' => __( 'General Email', 'mailpoet' ),
 'description' => __( 'A general template for emails.', 'mailpoet' ),
 );
 $this->templates['simple-light'] = array(
 'title' => __( 'Simple Light', 'mailpoet' ),
 'description' => __( 'A basic template with header and footer.', 'mailpoet' ),
 );
 }
 private function initialize_api(): void {
 register_post_meta(
 'wp_template',
 self::MAILPOET_EMAIL_META_THEME_TYPE,
 array(
 'show_in_rest' => array(
 'schema' => ( new Email_Styles_Schema() )->get_schema(),
 ),
 'single' => true,
 'type' => 'object',
 'default' => self::MAILPOET_TEMPLATE_EMPTY_THEME,
 )
 );
 register_rest_field(
 'wp_template',
 self::MAILPOET_EMAIL_META_THEME_TYPE,
 array(
 'get_callback' => function ( $item ) {
 return $this->get_block_template_theme( $item['id'], $item['wp_id'] );
 },
 'update_callback' => function ( $value, $template ) {
 return update_post_meta( $template->wp_id, self::MAILPOET_EMAIL_META_THEME_TYPE, $value );
 },
 'schema' => ( new Email_Styles_Schema() )->get_schema(),
 )
 );
 }
 private function get_block_templates() {
 $block_templates = array_map(
 function ( $template_slug ) {
 return $this->get_block_template_from_file( $template_slug . '.html' );
 },
 array_keys( $this->templates )
 );
 $custom_templates = $this->get_custom_templates(); // From the DB.
 $custom_template_ids = wp_list_pluck( $custom_templates, 'id' );
 // Combine to remove duplicates if a custom template has the same ID as a file template.
 return array_column(
 array_merge(
 $custom_templates,
 array_filter(
 $block_templates,
 function ( $block_template ) use ( $custom_template_ids ) {
 return ! in_array( $block_template->id, $custom_template_ids, true );
 }
 ),
 ),
 null,
 'id'
 );
 }
 private function get_block_template_from_file( string $template ) {
 $template_slug = $this->utils->get_block_template_slug_from_path( $template );
 $template_object = (object) array(
 'slug' => $template_slug,
 'id' => $this->plugin_slug . '//' . $template_slug,
 'title' => $this->templates[ $template_slug ]['title'] ?? '',
 'description' => $this->templates[ $template_slug ]['description'] ?? '',
 'path' => $this->template_directory . $template,
 'type' => 'wp_template',
 'theme' => $this->plugin_slug,
 'source' => 'plugin',
 'post_types' => array(
 $this->post_type,
 ),
 );
 return $this->utils->build_block_template_from_file( $template_object );
 }
 private function get_custom_templates( $slugs = array(), $template_type = 'wp_template' ): array {
 $check_query_args = array(
 'post_type' => $template_type,
 'posts_per_page' => -1,
 'no_found_rows' => true,
 'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
 array(
 'taxonomy' => 'wp_theme',
 'field' => 'name',
 'terms' => array( $this->plugin_slug, get_stylesheet() ),
 ),
 ),
 );
 if ( is_array( $slugs ) && count( $slugs ) > 0 ) {
 $check_query_args['post_name__in'] = $slugs;
 }
 $check_query = new \WP_Query( $check_query_args );
 $custom_templates = $check_query->posts;
 return array_map(
 function ( $custom_template ) {
 return $this->utils->build_block_template_from_post( $custom_template );
 },
 $custom_templates
 );
 }
 private function get_custom_template_theme( ?int $template_wp_id ): ?array {
 if ( ! $template_wp_id ) {
 return null;
 }
 $theme = get_post_meta( $template_wp_id, self::MAILPOET_EMAIL_META_THEME_TYPE, true );
 if ( is_array( $theme ) && isset( $theme['styles'] ) ) {
 return $theme;
 }
 return null;
 }
}
