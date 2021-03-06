{include:{$BACKEND_CORE_PATH}/Layout/Templates/Head.tpl}
{include:{$BACKEND_CORE_PATH}/Layout/Templates/StructureStartModule.tpl}

<div class="pageTitle">
	<h2>{$lblSentMailings|ucfirst}</h2>
</div>

{form:edit}
	<div class="box horizontal">
		<div class="heading ">
			<h3>{$lblEditCampaign|ucfirst}</h3>
		</div>
		<div class="options">
			<p>
				<label for="campaigns">{$lblCampaign|ucfirst}</label>
				{$ddmCampaigns} {$ddmCampaignsError}
			</p>
		</div>
	</div>

	<div class="fullwidthOptions">
		<div class="buttonHolderRight">
			<input id="editButton" class="inputButton button mainButton" type="submit" name="add" value="{$lblSave|ucfirst}" />
		</div>
	</div>
{/form:edit}

{include:{$BACKEND_CORE_PATH}/Layout/Templates/StructureEndModule.tpl}
{include:{$BACKEND_CORE_PATH}/Layout/Templates/Footer.tpl}
