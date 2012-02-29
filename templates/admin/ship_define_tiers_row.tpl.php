<div class="registry_row">
		<p class="title" style="cursor: pointer;" <?php $this->pxyGRView->RenderAsEvents($_ITEM->Rowid); ?> ><?= $_ITEM->RegistryName ?></p>
		<div class="right">
			<p class="expiry"><?= $_ITEM->EventDate ?></p>
			<p class="edit"><a href="#"<?php $this->pxyGREdit->RenderAsEvents($_ITEM->Rowid); ?>><img src="<?= templateNamed('css/images/btn_edit.png') ?>" alt="<?php _xt('View') ?>"/></a></p>
		</div>
</div>