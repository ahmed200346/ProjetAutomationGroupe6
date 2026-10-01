<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Handles custom field typea.
 */
class WpGetApi_Parameter_Field extends CMB2_Type_Base {


	public static function init_parameter() {
		add_filter( 'cmb2_render_class_parameter', array( __CLASS__, 'class_name' ) );
		add_filter( 'cmb2_sanitize_parameter', array( __CLASS__, 'maybe_save_split_values' ), 12, 4 );
		/**
		 * The following snippets are required for allowing the users field
		 * to work as a repeatable field, or in a repeatable group
		 */
		add_filter( 'cmb2_sanitize_parameter', array( __CLASS__, 'sanitize' ), 10, 5 );
		add_filter( 'cmb2_types_esc_parameter', array( __CLASS__, 'escape' ), 10, 4 );
	}

	public static function class_name() {
		return __CLASS__; }


	/**
	 * Handles outputting the users field.
	 */
	public function render() {

		// make sure we assign each part of the value we need.
		$value = wp_parse_args(
			$this->field->escaped_value(),
			array(
				'name'  => '',
				'value' => '',
			)
		);

		ob_start();
		// Do html
		?>

		<div class="name input-wrap">
			<label for="<?php echo esc_attr( $this->_id( '_name' ) ); ?>">
				<?php echo esc_html( $this->_text( 'parameter_name_text', esc_html__( 'Name', 'wpgetapi' ) ) ); ?>
			</label>

			<?php
			$args = array(
				'name'        => esc_attr( $this->_name( '[name]' ) ),
				'id'          => esc_attr( $this->_id( '_name' ) ),
				'value'       => esc_attr( $value['name'] ),
				'desc'        => '',
				'placeholder' => esc_html__( 'Name of the parameter', 'wpgetapi' ),
			);
			if ( $this->field->args( 'repeatable' ) ) {
				$args['data-iterator'] = esc_attr( $this->types->iterator );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignored since the data is printed input html content.
			echo $this->types->input( $args );
			?>
		</div><span class="colon">:</span><div class="value input-wrap">
			<label for="<?php echo esc_attr( $this->_id( '_value' ) ); ?>">
				<?php echo esc_html( $this->_text( 'parameter_value_text', __( 'Value', 'wpgetapi' ) ) ); ?>
			</label>

			<?php
			$args = array(
				'name'        => esc_attr( $this->_name( '[value]' ) ),
				'id'          => esc_attr( $this->_id( '_value' ) ),
				'value'       => esc_attr( wp_unslash( $value['value'] ) ),
				'desc'        => '',
				'rows'        => 1,
				'placeholder' => esc_html__( 'Value of the parameter', 'wpgetapi' ),
			);
			if ( $this->field->args( 'repeatable' ) ) {
				$args['data-iterator'] = esc_attr( $this->types->iterator );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ignored since the data is printed input html content.
			echo $this->types->textarea( $args );
			?>
		</div>
		
		<?php

		// grab the data from the output buffer.
		return $this->rendered( ob_get_clean() );
	}

	/**
	 * Generate field id attribute
	 * Copy and then tweak the CMB2_Types class's id() method from src/lib/cmb2/includes/CMB2Types.php
	 *
	 * @since  1.1.0
	 * @param  string $suffix                     For multi-part fields
	 * @param  bool   $append_repeatable_iterator Whether to append the iterator attribute if the field is repeatable.
	 * @return string                             Id attribute
	 */
	private function _id( $suffix = '', $append_repeatable_iterator = true ) {
		$id = $this->types->field->id() . $suffix . ( $this->types->field->args( 'repeatable' ) ? '_' . $this->types->iterator : '' );
		return $id;
	}


	/**
	 * Optionally save the values into separate fields
	 */
	public static function maybe_save_split_values( $override_value, $value, $object_id, $field_args ) {

		// Don't do the override
		if ( ! isset( $field_args['split_values'] ) || ! $field_args['split_values'] ) {
			return $override_value;
		}

		$encryption = new WpGetApi_Encryption();

		$parameter_keys = array( 'name', 'value' );
		foreach ( $parameter_keys as $key ) {
			if ( ! empty( $value[ $key ] ) ) {
				$updated_value = $encryption->encrypt( $value[ $key ] );
				update_post_meta( $object_id, $field_args['id'] . 'parameter_' . $key, $updated_value );
			}
		}

		remove_filter( 'cmb2_sanitize_parameter', array( __CLASS__, 'sanitize' ), 10, 5 );
		// Tell CMB2 we already did the update
		return true;
	}


	public static function sanitize( $check, $meta_value, $object_id, $field_args, $sanitize_object ) {

		// if not repeatable, bail out.
		if ( ! is_array( $meta_value ) || ! $field_args['repeatable'] ) {
			return $check;
		}

		$encryption = new WpGetApi_Encryption();

		foreach ( $meta_value as $i => $param ) {

			// if both empty, unset and continue
			if ( $param['name'] == '' && $param['value'] == '' ) {
				unset( $meta_value[ $i ] );
				continue;
			}

			foreach ( $param as $key => $val ) {
				$meta_value[ $i ][ $key ] = $key === 'name' ? $val : $encryption->encrypt( $val );
			}
		}

		return array_filter( $meta_value );
	}


	public static function escape( $check, $meta_value, $field_args, $field_object ) {

		// if not repeatable, bail out.
		if ( ! is_array( $meta_value ) || ! $field_args['repeatable'] ) {
			return $check;
		}

		$encryption = new WpGetApi_Encryption();

		foreach ( $meta_value as $i => $param ) {

			// if both empty, unset and continue
			if ( $param['name'] == '' && $param['value'] == '' ) {
				unset( $meta_value[ $i ] );
				continue;
			}

			foreach ( $param as $key => $val ) {
				$meta_value[ $i ][ $key ] = $key === 'name' ? $val : $encryption->decrypt( $val );
			}
		}

		return array_filter( $meta_value );
	}
}
