/**
 * Javascript file for Category Show.
 * It requires jQuery.
 */

function wpcs_gen_tag() {
	// Category Show searches for term_id since 0.4.1 and not term slug.
	// There is a need to add the id%% tag to be compatible with other versions
	$("#wpcs_gen_tag").val("%%wpcs-"+$("#wpcs_term_dropdown").val()+"%%"+$("#wpcs_order_type").val()+$("#wpcs_order_by").val()+"%%id%%");
	$("#wpcs_gen_tag").select();
	$("#wpcs_gen_tag").focus();
}