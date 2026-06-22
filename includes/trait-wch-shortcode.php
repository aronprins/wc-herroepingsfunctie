<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WCH_Shortcode {
	/* --------------------------------------------------------------------- *
	 *  Frontend formulier.
	 * --------------------------------------------------------------------- */

	public function render_shortcode( $atts ) {
		$settings = $this->get_settings();
		$nonce    = wp_create_nonce( 'wch_nonce' );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$app_id   = wp_unique_id( 'wch-app-' );

		ob_start();
		?>
		<div class="wch-wrap" id="<?php echo esc_attr( $app_id ); ?>">
			<style>
				.wch-wrap{max-width:640px;margin:1.5em 0;font-size:16px;line-height:1.5;}
				.wch-wrap h3{margin:0 0 .5em;}
				.wch-step{display:none;}
				.wch-step.is-active{display:block;}
				.wch-field{margin-bottom:1em;}
				.wch-field label{display:block;font-weight:600;margin-bottom:.25em;}
				.wch-field input[type=text],.wch-field input[type=email],.wch-field textarea{width:100%;padding:.6em;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;}
				.wch-btn{display:inline-block;padding:.7em 1.4em;border:0;border-radius:6px;cursor:pointer;font-size:1em;}
				.wch-btn-primary{background:#1f2d3d;color:#fff;}
				.wch-btn-secondary{background:#e9ecef;color:#1f2d3d;margin-right:.5em;}
				.wch-item{display:flex;align-items:flex-start;gap:.6em;padding:.6em 0;border-bottom:1px solid #eee;}
				.wch-item label{font-weight:400;}
				.wch-notice{padding:.8em 1em;border-radius:6px;margin:1em 0;}
				.wch-error{background:#fdecea;color:#611a15;}
				.wch-success{background:#edf7ed;color:#1e4620;}
				.wch-muted{color:#666;font-size:.9em;}
				.wch-summary{background:#f7f7f8;padding:1em;border-radius:6px;}
				.wch-hidden{display:none;}
			</style>

			<div class="wch-notice wch-error wch-hidden" data-role="error"></div>

			<!-- Stap 1: identificatie -->
			<div class="wch-step is-active" data-step="1">
				<h3><?php esc_html_e( 'Bestelling herroepen', 'wc-herroepingsfunctie' ); ?></h3>
				<p><?php echo esc_html( $settings['intro_tekst'] ); ?></p>
				<div class="wch-field">
					<label for="wch-order"><?php esc_html_e( 'Ordernummer', 'wc-herroepingsfunctie' ); ?></label>
					<input type="text" id="wch-order" autocomplete="off">
				</div>
				<div class="wch-field">
					<label for="wch-email"><?php esc_html_e( 'E-mailadres van de bestelling', 'wc-herroepingsfunctie' ); ?></label>
					<input type="email" id="wch-email" autocomplete="email">
				</div>
				<button type="button" class="wch-btn wch-btn-primary" data-action="lookup">
					<?php esc_html_e( 'Bestelling ophalen', 'wc-herroepingsfunctie' ); ?>
				</button>
			</div>

			<!-- Stap 2: selectie -->
			<div class="wch-step" data-step="2">
				<h3><?php esc_html_e( 'Wat wilt u herroepen?', 'wc-herroepingsfunctie' ); ?></h3>
				<p class="wch-muted"><?php esc_html_e( 'Selecteer de producten waarvoor u de overeenkomst wilt herroepen. U bent niet verplicht een reden op te geven.', 'wc-herroepingsfunctie' ); ?></p>
				<div data-role="items"></div>
				<div class="wch-field" style="margin-top:1em;">
					<label for="wch-reason"><?php esc_html_e( 'Reden (optioneel)', 'wc-herroepingsfunctie' ); ?></label>
					<textarea id="wch-reason" rows="3"></textarea>
				</div>
				<button type="button" class="wch-btn wch-btn-secondary" data-action="back"><?php esc_html_e( 'Terug', 'wc-herroepingsfunctie' ); ?></button>
				<button type="button" class="wch-btn wch-btn-primary" data-action="toconfirm"><?php esc_html_e( 'Doorgaan', 'wc-herroepingsfunctie' ); ?></button>
			</div>

			<!-- Stap 3: bevestigen -->
			<div class="wch-step" data-step="3">
				<h3><?php esc_html_e( 'Herroeping bevestigen', 'wc-herroepingsfunctie' ); ?></h3>
				<p><?php esc_html_e( 'Hierbij deelt u mede dat u de overeenkomst voor de onderstaande producten herroept. U ontvangt direct een ontvangstbevestiging per e-mail.', 'wc-herroepingsfunctie' ); ?></p>
				<div class="wch-summary" data-role="summary"></div>
				<p style="margin-top:1em;">
					<button type="button" class="wch-btn wch-btn-secondary" data-action="back2"><?php esc_html_e( 'Terug', 'wc-herroepingsfunctie' ); ?></button>
					<button type="button" class="wch-btn wch-btn-primary" data-action="submit"><?php echo esc_html( $settings['confirm_knop_tekst'] ); ?></button>
				</p>
			</div>

			<!-- Stap 4: klaar -->
			<div class="wch-step" data-step="4">
				<div class="wch-notice wch-success" data-role="done"></div>
			</div>
		</div>

		<script>
		(function(){
			var app = document.getElementById(<?php echo wp_json_encode( $app_id ); ?>);
			if(!app || app.dataset.init){ return; }
			app.dataset.init = '1';

			var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var state   = { order:'', email:'', customerName:'', items:[], selected:[] };

			function $(sel){ return app.querySelector(sel); }
			function show(step){
				app.querySelectorAll('.wch-step').forEach(function(s){ s.classList.remove('is-active'); });
				app.querySelector('[data-step="'+step+'"]').classList.add('is-active');
			}
			function err(msg){
				var box = app.querySelector('[data-role=error]');
				if(!msg){ box.classList.add('wch-hidden'); return; }
				box.textContent = msg; box.classList.remove('wch-hidden');
			}
			function post(action, data){
				var body = new URLSearchParams();
				body.append('action', action);
				body.append('nonce', nonce);
				Object.keys(data).forEach(function(k){
					if(Array.isArray(data[k])){ data[k].forEach(function(v){ body.append(k+'[]', v); }); }
					else { body.append(k, data[k]); }
				});
				return fetch(ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString()})
					.then(function(r){ return r.json(); });
			}
			function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

			app.addEventListener('click', function(e){
				var a = e.target.getAttribute('data-action');
				if(!a){ return; }

				if(a==='lookup'){
					err('');
					state.order = $('#wch-order').value.trim();
					state.email = $('#wch-email').value.trim();
					if(!state.order || !state.email){ err('Vul uw ordernummer en e-mailadres in.'); return; }
					e.target.disabled = true;
					post('wch_lookup', {order_number:state.order, email:state.email}).then(function(res){
						e.target.disabled = false;
						if(!res || !res.success){ err(res && res.data ? res.data.message : 'Er ging iets mis.'); return; }
						state.customerName = res.data.customer_name || '';
						state.items = res.data.items;
						var html = '';
						state.items.forEach(function(it){
							html += '<div class="wch-item">'
								+ '<input type="checkbox" id="wch-it-'+esc(it.id)+'" value="'+esc(it.id)+'" data-itemcb>'
								+ '<label for="wch-it-'+esc(it.id)+'">'+esc(it.name)+' &times; '+esc(it.qty)+'</label>'
								+ '</div>';
						});
						app.querySelector('[data-role=items]').innerHTML = html;
						show(2);
					}).catch(function(){ e.target.disabled=false; err('Er ging iets mis. Probeer het later opnieuw.'); });
				}

				if(a==='back'){ err(''); show(1); }
				if(a==='back2'){ err(''); show(2); }

				if(a==='toconfirm'){
					err('');
					var cbs = app.querySelectorAll('[data-itemcb]:checked');
					if(cbs.length === 0){ err('Selecteer minimaal één product om te herroepen.'); return; }
					state.selected = Array.prototype.map.call(cbs, function(c){ return c.value; });
					var names = state.items.filter(function(it){ return state.selected.indexOf(String(it.id)) > -1; });
					var sum = '<p><strong>Naam:</strong> '+esc(state.customerName || '-')+'<br>'
						+ '<strong>E-mailadres voor bevestiging:</strong> '+esc(state.email)+'</p>';
					sum += '<ul style="margin:.5em 0 .5em 1.2em;">';
					names.forEach(function(it){ sum += '<li>'+esc(it.name)+' &times; '+esc(it.qty)+'</li>'; });
					sum += '</ul>';
					var reason = $('#wch-reason').value.trim();
					if(reason){ sum += '<p><strong>Reden:</strong> '+esc(reason)+'</p>'; }
					sum += '<p class="wch-muted">Bestelling: '+esc(state.order)+'</p>';
					app.querySelector('[data-role=summary]').innerHTML = sum;
					show(3);
				}

				if(a==='submit'){
					err('');
					e.target.disabled = true;
					post('wch_submit', {
						order_number: state.order,
						email: state.email,
						reason: $('#wch-reason').value.trim(),
						item_ids: state.selected
					}).then(function(res){
						e.target.disabled = false;
						if(!res || !res.success){ err(res && res.data ? res.data.message : 'Er ging iets mis.'); show(2); return; }
						app.querySelector('[data-role=done]').innerHTML = esc(res.data.message);
						show(4);
					}).catch(function(){ e.target.disabled=false; err('Er ging iets mis. Probeer het later opnieuw.'); show(2); });
				}
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
