<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<p class="lead"><?php esc_html_e( 'Set your preferences below', 'front-end-pm' ); ?></p>
<?php echo fep_info_output(); ?>
<?php echo Fep_Form::init()->form_field_output( 'settings' ); ?>
