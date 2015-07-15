(function($) {
    
	$(document).ready(function(){

		$('#updateoffers').click(function() {
			$(this).parents('li').hide();
			$('#ymlprogress').show();

			window.offerunlock = 'yes';

			updateoffers();

			return false;

		});


		var updateoffers = function(){

			var data = {action: $('.woocommerce form input[name="key_source"]').val()+'_ajaxUpdateOffers', unlock: offerunlock};

			console.log(data);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: data,
				success: function(data){

					console.log(data);

					window.offerunlock = 'no';

					if( !data.ismakeyml ){
						updateoffers();
					}else{
						$('#ymlprogress').hide();
						$('#updateoffers').parents('li').show();
					}
				}
			})
			.done(function() {
				console.log("success");
			})
			.fail(function(data) {
				$.post(ajaxurl, {action: 'yml_send_log'});
				alert('Случилась беда. Логи работы скрипта уже ушли на почту разработчику, он их обрабатывает и скоро с Вами свяжется');
			})
			.always(function() {
				console.log("complete");
			});
		}


		$('#add_source').click(function(){

			var name = prompt( 'Введите имя нового источника' );

			if( typeof name != 'object' ){

				$.post(ajaxurl, {action: 'add_yml_source', name : name}, function(data, textStatus, xhr) {
					window.location.href = window.location.href;
				});

			}

			return false;
		});


		if( $('.woocommerce form input[name="key_surce"]').val() != '_yandex_market' )
			$('.woocommerce form p.submit input[name="save"]').replaceWith(
				'<input name="save" class="button-primary" type="submit" value="Сохранить изменения"> <input name="delete" class="button-primary delete" type="submit" value="Удалить">'
			);

		$('.woocommerce form p.submit input[name="delete"]').live('click', function(event) {

			if( confirm('Вы действительно хотите удалить источник?') ){
				$.post(ajaxurl, {action: 'del_yml_source', key : $('.woocommerce form input[name="key_source"]').val() }, function(data, textStatus, xhr) {
					window.location.href = window.location.href+"&source=_yandex_market";
				});				
			}


			return false;
		});


	});
})(jQuery);