/**
 * @file   modules/experience/js/experience_admin.js
 * @author CONORY (http://www.conory.com)
 * @brief  experience 모듈의 관리자용 javascript
 **/

jQuery(function($){
	$('button.calc_experience').click(function(){
		var $this, form, elems, reset, el, fn, i=0;
		
		$this = $(this);
		$expr = $('input.level_expression');
		form  = this.form;
		elems = form.elements;
		reset = $this.hasClass('_reset');

		if(reset || !$expr.val()) $expr.val('Math.pow(i,2) * 90');

		try {
			fn = new Function('i', 'return ('+$expr.val()+')');
		} catch(e){
			fn = null;
		}

		if(!fn) return;

		while(el = elems['level_step_'+(++i)]) el.value = fn(i);
	});
});

function syncExpPoint() {
	if(!confirm('경험치와 포인트 동기화를 시키면 경험치 데이터가 손실될 수 있습니다. 진행하시겠습니까?'))
	{
		return;
	}
	
    exec_xml(
		'experience',
		'procExperienceAdminSyncPoint',
		{},
		completeOn,
		['error','message']
	);
}

function syncMedal() {
	if(!confirm('기존의 메달 데이터를 삭제하게됩니다. 그래도 진행하시겠습니까?'))
	{
		return;
	}

	exec_xml(
		'experience',
		'procExperienceAdminSyncMedal',
		{},
		completeOn,
		['error','message']
	);
}

function updateExperience(member_srl)
{
	var $experience = jQuery('#experience_'+member_srl);
	get_by_id('update_member_srl').value = member_srl;
	get_by_id('update_experience').value = $experience.val();

    var hF = get_by_id('updateForm');
	hF.submit();
}

function completeOn(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];

    alert(message);
	location.reload();
}
