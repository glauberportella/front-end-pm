<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

echo fep_info_output();
if( ! fep_get_user_message_count( 'total' ) ) {
	echo '<div class="fep-error">' . apply_filters( 'fep_filter_messagebox_empty', esc_html__( 'No messages found.', 'front-end-pm' ), $action ) . '</div>';
	return;
}

do_action( 'fep_display_before_messagebox', $action ); ?>
<div class="fep-messagebox-search-form-div">
	<form id="fep-messagebox-search-form" action="">
		<input type="hidden" name="fepaction" value="messagebox" />
		<input type="search" class="form-control" name="fep-search" class="fep-messagebox-search-form-field" value="<?php echo isset( $_GET['fep-search'] ) ? esc_attr( $_GET['fep-search'] ): ""; ?>" placeholder="<?php _e( 'Search Messages', 'front-end-pm'); ?> <?php _e('(type and press enter)', 'front-end-pm') ?>" />
		<input type="hidden" name="feppage" value="1" />
	</form>
</div>
<form class="fep-message-table form" method="post" action="">
	<div class="fep-table fep-action-table">
		<div>
			<div class="fep-bulk-action">
				<select name="fep-bulk-action" class="form-control">
					<option value=""><?php esc_html_e( 'Bulk action', 'front-end-pm' ); ?></option>
					<?php foreach ( Fep_Messages::init()->get_table_bulk_actions() as $bulk_action => $bulk_action_display ) : ?>
						<option value="<?php echo esc_attr( $bulk_action ); ?>"><?php echo esc_html( $bulk_action_display ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<input type="hidden" name="token"  value="<?php echo fep_create_nonce( 'bulk_action' ); ?>"/>
				<button type="submit" class="fep-button fep-bulk-action-apply-button" name="fep_action" value="bulk_action"><?php esc_html_e( 'Apply', 'front-end-pm' ); ?></button>
			</div>
			<div class="fep-loading-gif-div"></div>
			<div class="fep-filter">
				<select onchange="if (this.value) window.location.href=this.value" class="form-control">
					<?php foreach ( Fep_Messages::init()->get_table_filters() as $filter => $filter_display ) : ?>
						<option value="<?php echo esc_url( add_query_arg( array( 'fep-filter' => $filter, 'feppage' => false ) ) ); ?>" <?php selected($g_filter, $filter);?>><?php echo esc_html( $filter_display ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</div>
	<?php if ( $messages->have_messages() ) {
		wp_enqueue_script( 'fep-cb-check-uncheck-all' ); ?>
		<div class="fep-cb-check-uncheck-all-div checkbox"><label><input type="checkbox" class="fep-cb-check-uncheck-all" /><?php esc_html_e( 'Check/Uncheck all', 'front-end-pm' ); ?></label></div>
		<div id="fep-table" class="fep-table fep-odd-even">
			<?php
			while( $messages->have_messages() ) {
				$messages->the_message(); ?>
				<div id="fep-message-<?php echo fep_get_the_id(); ?>" class="fep-table-row">
					<?php foreach ( Fep_Messages::init()->get_table_columns() as $column => $display ) : ?>
						<div class="fep-column fep-column-<?php echo esc_attr( $column ); ?>"><?php Fep_Messages::init()->get_column_content( $column ); ?></div>
					<?php endforeach; ?>
				</div>
				<?php
			} //endwhile
			?>
		</div>
		<?php
		echo fep_pagination( $total_message );
	} else {
		?><div class="fep-error"><?php esc_html_e( 'No messages found. Try different filter.', 'front-end-pm' ); ?></div><?php 
	}
?></form><?php

