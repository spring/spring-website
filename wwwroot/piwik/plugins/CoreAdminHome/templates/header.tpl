<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>{if !$isCustomLogo}Piwik &rsaquo; {/if}{'CoreAdminHome_Administration'|translate}</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="generator" content="Piwik - Open Source Web Analytics" />
<link rel="shortcut icon" href="plugins/CoreHome/templates/images/favicon.ico" />

{loadJavascriptTranslations plugins='CoreAdminHome'}

{include file="CoreHome/templates/js_global_variables.tpl"}
{include file="CoreHome/templates/js_css_includes.tpl"}
<!--[if IE]>
<link rel="stylesheet" type="text/css" href="themes/default/ieonly.css" />
<![endif]-->
{if isset($enableFrames) && !$enableFrames}
{literal}
	<style type="text/css">body { display : none; }</style>
{/literal}
{/if}
</head>
<body>
{if isset($enableFrames) && !$enableFrames}
{literal}
	<script type="text/javascript">
		if(self == top) {
			var theBody = document.getElementsByTagName('body')[0];
			theBody.style.display = 'block';
		} else {
			top.location = self.location;
		}
	</script>
{/literal}
{/if}
<div id="root">
{if !isset($showTopMenu) || $showTopMenu}
{include file="CoreHome/templates/top_bar.tpl"}
{/if}

<div id="header">
{include file="CoreHome/templates/logo.tpl"}
{if $showPeriodSelection}{include file="CoreHome/templates/period_select.tpl"}{/if}
{include file="CoreHome/templates/js_disabled_notice.tpl"}
</div>

{ajaxRequestErrorDiv}
{if !isset($showMenu) || $showMenu}
	{include file="CoreAdminHome/templates/menu.tpl"}
{/if}


<div id="content" class="admin">

{include file="CoreHome/templates/header_message.tpl"}

{if !empty($configFileNotWritable)}
<div class="ajaxSuccess" style="display:inline-block">
	{'General_ConfigFileIsNotWritable'|translate:"(config/config.ini.php)":"<br/>"}
</div>
{elseif strpos($url, 'updated=1')}	
<div class="ajaxSuccess" style="display:inline-block">
	{'General_YourChangesHaveBeenSaved'|translate}
</div>
{/if}

<div class="ui-confirm" id="alert">
    <h2></h2>
    <input id="yes" type="button" value="{'General_Ok'|translate}" />
</div>
