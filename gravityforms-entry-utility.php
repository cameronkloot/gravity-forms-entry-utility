<?php
/*
Plugin Name: Gravity Forms Entry Utility
Plugin URI: http://cameronkloot.com/plugins/gravityforms-entry-utility
Description: A Gravity Forms utility plugin for counting and exporting entries. 
Version: 0.0.1
Author: Cameron Kloot
Author URI: http://cameronkloot.com
*/

if( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'GFEntryUtil' ) ):

class GFEntryUtil {

	function __construct() {

	}

	public function initialize() {

		//: Add plugin check from the sweeney plugin ://

		add_action( 'admin_menu', array( $this, 'wp_admin_menu' ), 999 );

		add_action( 'admin_enqueue_scripts', array( $this, 'wp_admin_enqueue_scripts' ) );

		add_action( 'wp_ajax_gfe_util', array( $this, 'wp_ajax_gfe_util' ) );
		add_action( 'admin_footer', array( $this, 'wp_admin_footer_ajax_page_load' ) );
	}


	public function wp_admin_menu() {
		
		$util_capability = 'manage_options';
		$util_capability = apply_filters( 'gfe_util_capability', $util_capability );

		add_submenu_page( 'tools.php', 'Gravity Forms Entry Utility', 'Gravity Forms Entries', $util_capability, 'gfe_util', array( $this, 'util_page' ) );

	}

	public function wp_admin_enqueue_scripts() {
	    wp_enqueue_script( 'masked-inputs', plugin_dir_url( __FILE__ ) . 'inc/jquery.maskedinput.min.js' );

	}

	public function util_page() {
		?>
		<style type="text/css">
		.gfe-section {
			float: left;
			width: 100%;
			max-width: 594px;
		}
		.gfe-section .card {
			margin: 0 20px 20px 0;
		}
		.results-table {
			font-size: 16px;
			border-collapse: collapse;
			width: 100%;
		}
		.results-table tr:nth-of-type(2n+2) {
			background-color: #f1f1f1;
		}
		.results-table th,
		.results-table td {
			padding: 6px;
			text-align: left;
		}
		.results-table th.count,
		.results-table td.count {
			text-align: right;
		}
		#ajax-loader {
			display: none;
		}
		</style>
		<div class="wrap">
			<h1>Gravity Forms Entry Utility</h1>
			<div class="gfe-section left-section">
				<div class="card">
					<h2>Search Criteria</h2>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="gfe_start_date">Start Date</label></th>
								<td><input type="date" id="gfe_start_date" name="gfe_start_date" placeholder="mm/dd/yyyy"></td>
							</tr>
							<tr>
								<th scope="row"><label for="gfe_end_date">End Date</label></th>
								<td><input type="date" id="gfe_end_date" name="gfe_end_date" placeholder="mm/dd/yyyy" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ) ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="gfe_form">Form</label></th>
								<td>
									<select id="gfe_form_id" name="gfe_form_id">
										<option value="">All Forms</option>
										<?php foreach( GFAPI::get_forms() as $form ): ?>
											<option value="<?php echo esc_attr( $form['id'] ) ?>"><?php echo esc_html( $form['title'] ) ?></option>
										<?php endforeach; ?>
									</select>
									<!-- Will change to select2 object to pass multiple form ids -->
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="card">
					<h2>Actions</h2>
					<div class="actions">
						<p><a id="count-entries" class="" href="javascript:void(0);">Count Entries</a></p>
						<p><a id="export-entries" style="color:#555;cursor:not-allowed;" href="javascript:void(0);">Export Entries</a></p>
					</div>
				</div>
			</div>
			<div class="gfe-section right-section">
				<div class="card" style="display:none;">
					<h2>Results</h2>
					<img id="ajax-loader" src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ) ?>/inc/ajax-loader.gif">
					<div id="gfe-results"></div>
				</div>
			</div>
		</div>
		<?php
	}

	public function wp_ajax_gfe_util() {
		
		$criteria = $_POST['criteria'];

		//: Check if criteria exists and is in the correct format ://
		if ( !isset( $criteria ) || empty( $criteria ) || !is_array( $criteria ) ) die();
		
		//: Check for non-null method parameter exist ://
		if ( !isset( $criteria['method'] ) || empty( $criteria['method'] ) ) die();

		//: Check for criteria and set defaults ://
		$start_date = !empty( $criteria['start_date'] ) ? $criteria['start_date'] : date( 'Y-m-d', 0 );
		$end_date 	= !empty( $criteria['end_date'] ) 	? $criteria['end_date'] : current_time( 'Y-m-d' );

		$search_criteria = array(
			'start_date'	=> sanitize_text_field( $start_date ),
			'end_date'		=> sanitize_text_field( $end_date ),
		);

		//: Get form ids if not empty or set to all forms ://
		$forms = !empty( $criteria['form_id'] ) ? array( GFAPI::get_form( sanitize_key( $criteria['form_id'] ) ) ) : GFAPI::get_forms();

		$response = array(
			'criteria' => array(
				'method' 		=> sanitize_key( $criteria['method'] ),
				'start_date'	=> sanitize_text_field( $start_date ),
				'end_date'		=> sanitize_text_field( $end_date ),
			),
			'results' => array()
		);

		switch( sanitize_key( $criteria['method'] ) ) {
			
			//: Count entries ://
			case 'count':

				$total_count = 0;

				foreach ( $forms as $form ) {
					$entry_count = GFAPI::count_entries( $form['id'], $search_criteria );

					$response['results'][] = array(
						'id'			=> $form['id'],
						'title'			=> $form['title'],
						'entry_count' 	=> $entry_count
					);

					$total_count += $entry_count;

				}
				break;

			//: Export Entries ://
			case 'export':
				
				//: Todo ://
				die();

				break;

		}

		// $response['results']['total_entry_count'] = $total_count;
		
		wp_send_json( $response );

		die();
	}

	function wp_admin_footer_ajax_page_load() {
		?>
		<script type="text/javascript">
		;(function($) {
			$(document).ready(function($) {
				if (!checkDateInput()) {
					$('#gfe_start_date,#gfe_end_date').prop('type','text');
					$('#gfe_start_date,#gfe_end_date').mask('99/99/9999',{placeholder:'mm/dd/yyyy'});
				}
				
				function checkDateInput() {
				    var input = document.createElement('input');
				    input.setAttribute('type','date');

				    var notADateValue = 'not-a-date';
				    input.setAttribute('value', notADateValue); 

				    return (input.value !== notADateValue);
				}

				var criteria = {
					start_date 	: $('#gfe_start_date').val(),
					end_date 	: $('#gfe_end_date').val(),
					form_id		: $('#gfe_form_id option:selected').val(),
				}

				$('#count-entries').click(function(e){
					criteria['method'] = 'count';
					runUtilAjax();
					$('#gfe-results').closest('.card').css('display','block');
					$('#ajax-loader').css('display','block');
					e.preventDefault();
				});
				
				function runUtilAjax(){
					$.post( ajaxurl, {
						'action'	: 'gfe_util',
				        'criteria'		: criteria
				    }, function(response){
				    	if(response && response['results'] && response['criteria']){
				    		if (response['criteria']['method'] == 'count') {
				    			var results = response['results'],
				    				rows = '',
				    				exportUrl = '"Form","Count"\r\n';

					    		for(var i = 0;i<results.length;i++){
					    			rows += '<tr><td>' + results[i]['title'] + '</td><td class="count">' + results[i]['entry_count'] + '</td></tr>';
					    			rowExport = '"' + results[i]['title'] + '","' + results[i]['entry_count'] + '"' + "\r\n";
					    			exportUrl += rowExport;
					    		}
					    	
								$('#gfe-results').append('<table class="results-table"><tbody><tr><th>Form</th><th class="count">Count</th></tr>' + rows + '</tbody></table>');

								exportUrl = 'data:application/csv;charset=utf-8,' + encodeURIComponent( exportUrl );
								var exportLink = $('<a style="display:inline-block;margin:10px 0;">Download CSV</a>').attr({
						            'download': 'test.csv',
						                'href': exportUrl,
						                'target': '_blank'
						        });
						        $('#gfe-results').append(exportLink);
						        $('#ajax-loader').css('display','none');
				    		}
				    	}
				    }
				);
				}
			});

		})(jQuery);
		</script>
		<?php
	}




} //: END GFEntryUtil class ://

function GFEntryUtil() {
	
	global $GFEntryUtil;
	
	if( !isset( $GFEntryUtil ) ) {
		$GFEntryUtil = new GFEntryUtil();
		$GFEntryUtil->initialize();	
	}
	
	return $GFEntryUtil;

} GFEntryUtil();

endif;
