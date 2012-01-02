<div class="dataTableFeatures">

{if $properties.show_offset_information}
<span>
	<span class="dataTablePages"></span>
</span>
{/if}

{if $properties.show_pagination_control}
<span>
	<span class="dataTablePrevious">&lsaquo; {if isset($javascriptVariablesToSet.dataTablePreviousIsFirst)}{'General_First'|translate}{else}{'General_Previous'|translate}{/if} </span> 
	<span class="dataTableNext">{'General_Next'|translate} &rsaquo;</span>
</span>
{/if}

{if $properties.show_search}
<span class="dataTableSearchPattern">
	<input id="keyword" type="text" length="15" />
	<input type="submit" value="{'General_Search'|translate}" />
</span>
{/if}

<span class="loadingPiwik" style='display:none'><img src="themes/default/images/loading-blue.gif" /> {'General_LoadingData'|translate}</span>
{if $properties.show_footer_icons}
	<div class="dataTableFooterIcons">
		<div class="dataTableFooterWrap" var="{$javascriptVariablesToSet.viewDataTable}">
			<img src="themes/default/images/data_table_footer_active_item.png" class="dataTableFooterActiveItem" />
			<div class="tableIconsGroup">
            	<span class="tableAllColumnsSwitch">
                    {if $properties.show_table}
                    <a class="tableIcon" format="table" var="table"><img title="{'General_DisplaySimpleTable'|translate}" src="themes/default/images/table.png" /></a>
                    {/if}
                    {if $properties.show_table_all_columns}
                    <a class="tableIcon" format="tableAllColumns" var="tableAllColumns"><img title="{'General_DisplayTableWithMoreMetrics'|translate}" src="themes/default/images/table_more.png" /></a>
                    {/if}
                    {if $properties.show_goals}
					<a class="tableIcon" format="tableGoals" var="tableGoals"><img title="{'General_DisplayTableWithGoalMetrics'|translate}" src="themes/default/images/{if $javascriptVariablesToSet.filter_only_display_idgoal=='ecommerceOrder'}ecommerceOrder.gif{else}goal.png{/if}" /></a>
                    {/if}
                    {if $properties.show_ecommerce}
                    <a class="tableIcon" format="ecommerceOrder" var="ecommerceOrder"><img title="{'General_EcommerceOrders'|translate}" src="themes/default/images/ecommerceOrder.gif" /> <span>{'General_EcommerceOrders'|translate}</span></a>
                    <a class="tableIcon" format="ecommerceAbandonedCart" var="ecommerceAbandonedCart"><img title="{'General_AbandonedCarts'|translate}" src="themes/default/images/ecommerceAbandonedCart.gif" /> <span>{'General_AbandonedCarts'|translate}</span></a>
                    {/if}
                </span>
           </div>
            {if $properties.show_all_views_icons}
			<div class="tableIconsGroup">
            	<span class="tableGraphViews tableGraphCollapsed">
                    <a class="tableIcon" format="graphVerticalBar" var="generateDataChartVerticalBar"><img width="16" height="16" src="themes/default/images/chart_bar.png" title="{'General_VBarGraph'|translate}" /></a>
                    <a class="tableIcon" format="graphPie" var="generateDataChartPie"><img width="16" height="16" src="themes/default/images/chart_pie.png" title="{'General_Piechart'|translate}" /></a>
                    <a class="tableIcon" format="cloud" var="cloud"><img width="16" height="16" src="themes/default/images/tagcloud.png" title="{'General_TagCloud'|translate}" /></a>
				</span>
           </div>
           {elseif $javascriptVariablesToSet.viewDataTable == "generateDataChartEvolution"}
			<div class="tableIconsGroup">
            	<span class="tableGraphViews">
                    <a class="tableIcon" format="graphEvolution" var="generateDataChartEvolution"><img width="16" height="16" src="themes/default/images/chart_bar.png" title="{'General_VBarGraph'|translate}" /></a>
				</span>
           </div>
           
           {/if}			
           
			<div class="tableIconsGroup">
				<span class="exportToFormatIcons"><a class="tableIcon" var="export"><img width="16" height="16" src="themes/default/images/export.png" title="{'General_ExportThisReport'|translate}" /></a></span>
				<span class="exportToFormatItems" style="display:none"> 
					{'General_Export'|translate}: 
					<a target="_blank" methodToCall="{$properties.apiMethodToRequestDataTable}" format="CSV" filter_limit="100">CSV</a> | 
					<a target="_blank" methodToCall="{$properties.apiMethodToRequestDataTable}" format="TSV" filter_limit="100">TSV (Excel)</a> | 
					<a target="_blank" methodToCall="{$properties.apiMethodToRequestDataTable}" format="XML" filter_limit="100">XML</a> |
					<a target="_blank" methodToCall="{$properties.apiMethodToRequestDataTable}" format="JSON" filter_limit="100">Json</a> |
					<a target="_blank" methodToCall="{$properties.apiMethodToRequestDataTable}" format="PHP" filter_limit="100">Php</a>
					{if $properties.show_export_as_rss_feed}
						| <a target="_blank" methodToCall="{$properties.apiMethodToRequestDataTable}" format="RSS" filter_limit="100" date="last10"><img border="0" src="themes/default/images/feed.png" /></a>
					{/if}
				</span>
				{if $properties.show_export_as_image_icon}
					<span id="dataTableFooterExportAsImageIcon">
						<a class="tableIcon" href="#" onclick="$('#{$chartDivId}').trigger('piwikExportAsImage'); return false;"><img title="{'General_ExportAsImage_js'|translate}" src="themes/default/images/image.png" /></a>
					</span>
				{/if}
			</div>
			
		</div>
			
		{if $properties.show_exclude_low_population}
			<span class="dataTableExcludeLowPopulation"></span>
		{/if}
	</div>
{/if}

{if !empty($properties.show_footer_message)}
	<div class='datatableFooterMessage'>{$properties.show_footer_message}</div>
{/if}

</div>

<div class="dataTableSpacer"></div>
