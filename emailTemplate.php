<?php
$emailTemplatePrepend = <<<'EOD'
<style type="text/css">
#emailcontent > p:first-of-type {margin-top: 25px}
.void {
margin: 0;
padding: 0;
font-size: 0;
}

@media only screen and (max-width: 600px) {
body {
background-color: #F3F4F6 !important;
max-width: 100% !important;
color: #2B3864;
}

.m-p-both {
padding-top: 20px !important;
padding-bottom: 30px !important;
}

.f-mobile-22 {
font-size: 22px !important;
}

.m-left {
text-align: left !important;
}
}

@media only screen and (max-width: 600px) {
.mobile-p-20 {
padding-left: 20px !important;
padding-right: 20px !important;
}

.mobile-fs-14 {
font-size: 14px !important;
line-height: 18px;
}

.mail-size {
width: 100% !important;
max-width: 100% !important;
min-width: auto !important;
}

.mail-size {
width: 100% !important;
}

.mobile-fs-19 {
font-size: 19px !important;
line-height: 24px;
}

.m-center {
text-align: center !important;
}

.pt {
padding-top: 0 !important;
}

.first-col {
padding-right: 0 !important;
padding-bottom: 25px;
}

.second-col {
padding-left: 0 !important;
}
}

@media only screen and (max-width: 600px) {
.mobile-hide {
display: none !important;
}
}

@media only screen and (max-width: 600px) {
img {
max-width: 100% !important;
height: auto !important;
}
}

@media only screen and (max-width: 600px) {
body {
width: 100% !important;
max-width: 100% !important;
}
}

@media only screen and (max-width: 600px) {
.responsive {
display: block !important;
width: 100% !important;
}
}
</style>
<div class="body" style="margin: 0; padding: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; font-family: &#39;Catamaran&#39;, Arial, Helvetica, sans-serif !important; font-weight: normal; margin-top: 0; margin-bottom: 0; margin-right: 0; margin-left: 0; padding-top: 0; padding-bottom: 0; padding-right: 0; padding-left: 0; color: #282828; background-color: #F3F4F6;text-align: center;">
<!--[if mso]>
<style type="text/css">
body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
</style>
<![endif]-->
<!-- center table -->
<table border="0" class="mail-size" cellpadding="0" cellspacing="0" width="630" align="center" style="width: 630px;">
<tbody><tr>
<td class="void center mail-size" align="center" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;background-color:#F3F4F6;font-size: 0;text-align: center;width: 630px;max-width: 630px;min-width: 630px;" width="630">
<table border="0" cellpadding="0" cellspacing="0" width="630" class="mail-size m-center responsive" style="margin: 0 auto; margin-top: 0; margin-bottom: 0; margin-right: auto; margin-left: auto;">
<tbody><tr>
<td class="void" width="15" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
<!-- content -->
<td class="void mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;width: 600px;" width="600">

<table border="0" cellpadding="0" cellspacing="0" width="100%" align="center" class="mail-size m-center responsive">

<tbody><tr>

<!-- wrap -->
<td width="600" valign="top" class="void mail-size" align="center" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;background-color: #F3F4F6;">

<!-- content -->
<table width="600" class="mail-size" border="0" cellspacing="0" cellpadding="0" align="center">
<!-- padding -->
<tbody><tr>
<td class="void" height="44" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
</tr>
<!--  header -->
<tr>
<td class="void mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;" width="600">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td align="left" class="void m-center mail-size" width="50%" style="margin: 0;padding: 0;background-color: #F3F4F6 !important;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;">
<img src="https://www.solarquotes.com.au/img/email/logo.png" alt=" logo" border="0" width="176" height="41">
</td>
<td align="right" class="void bg-f2f mobile-hide" width="50%" style="margin: 0;padding: 0;background-color: #F3F4F6 !important;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;color:#2B3864;font-size: 12px;text-align: right;font-family: Helvetica, Arial, sans-serif !important;">
<a href="{viewInBrowserURL}" style="text-decoration:none;color:#2B3864;font-size: 12px;text-align: right;" target="_blank"><span style="color:#2B3864;font-size: 12px;padding-right: 2px;"></span>
View this email in browser</a>
</td>
</tr>
</tbody></table>
</td>
</tr>
<tr>
<td class="void" height="34" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
</tr>
<!-- blue container -->
<tr>
<td class="void bg-l-blue" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-family: Helvetica, Arial, sans-serif !important;font-size: 0;background-color: #00a3d1;color: #b1c7e5 !important;" bgcolor="ffffff">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void center mobile-p-20 mail-size" background="https://www.solarquotes.com.au/img/email/bg-header2.png" width="600" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0px;padding-bottom: 0px;text-align: center;background-color: #2091cf;font-size: 0;color: #b1c7e5;vertical-align: top;">
<!--[if gte mso 9]>
<v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:600px;">
<v:fill type="tile" src="https://www.solarquotes.com.au/img/email/bg-header2.png" color="#7bceeb" />
<v:textbox style="mso-fit-shape-to-text:true;">
<![endif]-->

<div>
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;">
&nbsp;</td>
<td>
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void responsive light" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-left: 0px;padding-top: 50px;font-size: 10px;font-weight: 500;color: #b1c7e5;font-family: &#39;Helvetica&#39;, Arial, sans-serif !important;text-align: left;">
{templateSmallTitle}
</td>
</tr>
<tr>
<td class="void mobile-fs-19 responsive light" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-left: 0px;padding-top: 33px;font-size:26px;font-weight: bold;color: #ffffff;font-family: &#39;Helvetica&#39;, Arial, sans-serif !important;text-align: left;line-height: 30px;">
{templateBigTitle}
</td>
</tr>
<tr>
<td height="50" class="font-pr h2 white mobile-fs-19 responsive" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-left: 0px;font-size:26px;font-weight: bold;color: #FFFFFF !important;font-family: &#39;Helvetica&#39;, Arial, sans-serif !important;text-align: left;">
</td>
</tr>
</tbody></table>
</td>
<td class="void mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;">
&nbsp;</td>


</tr>

</tbody></table>
</div>
<!--[if gte mso 9]>
</v:textbox>
</v:rect>
<![endif]-->
</td>
</tr>

</tbody></table>
</td>
</tr>
<!-- article 1 -->
<tr>
<td class="void bg-l-blue mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;background-color: #ffffff;" width="600">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void bg-f2f mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;font-family: Helvetica, Arial, sans-serif !important;padding-right: 0;padding-left: 0;font-size: 0;" bgcolor="#ffffff">
&nbsp;</td>

<td id="emailcontent" class="void bg-f2f mail-size mobile-p-20" width="520" style="margin: 0;padding: 15px 0px;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 15px;padding-right: 0;padding-left: 0;font-size: 14px;color:#2B3864" bgcolor="#ffffff">
EOD;

$emailTemplateFinnSignature = <<< 'EOD'
<table><tbody>
<tr>
<td class="void" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-top: 0px;padding-bottom: 15px;padding-right: 0;padding-left: 0;font-size:14px;font-weight: bold;font-family: Helvetica, Arial, sans-serif !important;">
Best Regards</td>
</tr>
<tr>
<td class="void" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-top: 0px;padding-bottom: 0px;padding-right: 0;padding-left: 0;font-size:14px;font-weight: bold;font-family: Helvetica, Arial, sans-serif !important;text-align: left;">
<img border="0" src="https://www.solarquotes.com.au/img/email/sign.png" alt="logo" width="149" height="49">
</td>
</tr>
<tr>
<td class="void" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-top: 26px;padding-bottom: 5px;padding-right: 0;padding-left: 0;font-size:0px;font-weight: bold;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
</tr>
</tbody></table>
EOD;

$emailTemplateAppend = <<< 'EOD'
</td> <!-- emailcontent -->
<td class="void bg-l-blue mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;background-color: #ffffff;" bgcolor="#ffffff">
&nbsp;</td>
</tr>
</tbody></table>
</td>
</tr>
<!-- article 1 -->

<tr>
<td class="void mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-bottom: 30px;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;padding-top: 0px;" width="600">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void m-p-both responsive m-center" width="50%" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;font-family: Helvetica, Arial, sans-serif !important;padding-right: 0;padding-left: 0;font-size: 10px;background-color: #F3F4F6;color: #9DA5BA;padding-top: 30px;" bgcolor="#F3F4F6;">
{templateFooter}
</td>
<td class="void pt responsive m-center" width="50%" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-bottom: 0;font-family: Helvetica, Arial, sans-serif !important;padding-right: 0;padding-left: 0;font-size: 0;background-color: #F3F4F6;text-align:right;padding-top: 30px;" bgcolor="#F3F4F6;"><img border="0" src="https://www.solarquotes.com.au/img/email/logo-footer.png" alt="logo" width="153" height="36"></td>
</tr>
</tbody></table>
</td>
</tr>
</tbody></table>
</td>
</tr>
</tbody></table>
</td>
<td class="void" width="15" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
</tr>
</tbody></table>
</td>

<td class="void" width="15" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>

</tr>
</tbody></table>
<div class="gmailfix" style="font: 15px courier; white-space: nowrap; font-style: normal; font-variant: normal; font-weight: normal; font-size: 15px; font-family: courier; line-height: 0; display: none;">
&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
</div>
{templateAfterFooterContents}
</div>
EOD;

$emailTemplateWidePrepend = <<<'EOD'
<style type="text/css">
#emailcontent > p:first-of-type {margin-top: 25px}
.void {
margin: 0;
padding: 0;
font-size: 0;
}

@media only screen and (max-width: 800px) {
body {
background-color: #F3F4F6 !important;
max-width: 100% !important;
color: #2B3864;
}

.m-p-both {
padding-top: 20px !important;
padding-bottom: 30px !important;
}

.f-mobile-22 {
font-size: 22px !important;
}

.m-left {
text-align: left !important;
}
}

@media only screen and (max-width: 800px) {
.mobile-p-20 {
padding-left: 20px !important;
padding-right: 20px !important;
}

.mobile-fs-14 {
font-size: 14px !important;
line-height: 18px;
}

.mail-size {
width: 100% !important;
max-width: 100% !important;
min-width: auto !important;
}

.mail-size {
width: 100% !important;
}

.mobile-fs-19 {
font-size: 19px !important;
line-height: 24px;
}

.m-center {
text-align: center !important;
}

.pt {
padding-top: 0 !important;
}

.first-col {
padding-right: 0 !important;
padding-bottom: 25px;
}

.second-col {
padding-left: 0 !important;
}
}

@media only screen and (max-width: 800px) {
.mobile-hide {
display: none !important;
}
}

@media only screen and (max-width: 800px) {
img {
max-width: 100% !important;
height: auto !important;
}
}

@media only screen and (max-width: 800px) {
body {
width: 100% !important;
max-width: 100% !important;
}
}

@media only screen and (max-width: 800px) {
.responsive {
display: block !important;
width: 100% !important;
}
}
</style>
<div class="body" style="margin: 0; padding: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; font-family: &#39;Catamaran&#39;, Arial, Helvetica, sans-serif !important; font-weight: normal; margin-top: 0; margin-bottom: 0; margin-right: 0; margin-left: 0; padding-top: 0; padding-bottom: 0; padding-right: 0; padding-left: 0; color: #282828; background-color: #F3F4F6;text-align: center;">
<!--[if mso]>
<style type="text/css">
body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
</style>
<![endif]-->
<!-- center table -->
<table border="0" class="mail-size" cellpadding="0" cellspacing="0" width="830" align="center" style="width: 830px;">
<tbody><tr>
<td class="void center mail-size" align="center" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;background-color:#F3F4F6;font-size: 0;text-align: center;width: 830px;max-width: 830px;min-width: 830px;" width="830">
<table border="0" cellpadding="0" cellspacing="0" width="830" class="mail-size m-center responsive" style="margin: 0 auto; margin-top: 0; margin-bottom: 0; margin-right: auto; margin-left: auto;">
<tbody><tr>
<td class="void" width="15" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
<!-- content -->
<td class="void mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;width: 800px;" width="800">

<table border="0" cellpadding="0" cellspacing="0" width="100%" align="center" class="mail-size m-center responsive">

<tbody><tr>

<!-- wrap -->
<td width="800" valign="top" class="void mail-size" align="center" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;background-color: #F3F4F6;">

<!-- content -->
<table width="800" class="mail-size" border="0" cellspacing="0" cellpadding="0" align="center">
<!-- padding -->
<tbody><tr>
<td class="void" height="44" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
</tr>
<!--  header -->
<tr>
<td class="void mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;" width="800">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td align="left" class="void m-center mail-size" width="50%" style="margin: 0;padding: 0;background-color: #F3F4F6 !important;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;">
<img src="https://www.solarquotes.com.au/img/email/logo.png" alt=" logo" border="0" width="176" height="41">
</td>
<td align="right" class="void bg-f2f mobile-hide" width="50%" style="margin: 0;padding: 0;background-color: #F3F4F6 !important;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;color:#2B3864;font-size: 12px;text-align: right;font-family: Helvetica, Arial, sans-serif !important;">
<a href="{viewInBrowserURL}" style="text-decoration:none;color:#2B3864;font-size: 12px;text-align: right;" target="_blank"><span style="color:#2B3864;font-size: 12px;padding-right: 2px;"></span>
View this email in browser</a>
</td>
</tr>
</tbody></table>
</td>
</tr>
<tr>
<td class="void" height="34" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;">
&nbsp;</td>
</tr>
<!-- blue container -->
<tr>
<td class="void bg-l-blue" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-family: Helvetica, Arial, sans-serif !important;font-size: 0;background-color: #00a3d1;color: #b1c7e5 !important;" bgcolor="ffffff">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void center mobile-p-20 mail-size" background="https://www.solarquotes.com.au/img/email/bg-header2.png" width="800" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;background-size: cover;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0px;padding-bottom: 0px;text-align: center;background-color: #2091cf;font-size: 0;color: #b1c7e5;vertical-align: top;">
<!--[if gte mso 9]>
<v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:800px;">
<v:fill type="tile" src="https://www.solarquotes.com.au/img/email/bg-header2.png" color="#7bceeb" />
<v:textbox style="mso-fit-shape-to-text:true;">
<![endif]-->

<div>
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;">
&nbsp;</td>
<td>
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void responsive light" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-left: 0px;padding-top: 50px;font-size: 10px;font-weight: 500;color: #b1c7e5;font-family: &#39;Helvetica&#39;, Arial, sans-serif !important;text-align: left;">
{templateSmallTitle}
</td>
</tr>
<tr>
<td class="void mobile-fs-19 responsive light" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-left: 0px;padding-top: 33px;font-size:26px;font-weight: bold;color: #ffffff;font-family: &#39;Helvetica&#39;, Arial, sans-serif !important;text-align: left;line-height: 30px;">
{templateBigTitle}
</td>
</tr>
<tr>
<td height="50" class="font-pr h2 white mobile-fs-19 responsive" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;background-repeat: no-repeat;margin-left: 0;padding-left: 0px;font-size:26px;font-weight: bold;color: #FFFFFF !important;font-family: &#39;Helvetica&#39;, Arial, sans-serif !important;text-align: left;">
</td>
</tr>
</tbody></table>
</td>
<td class="void mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;">
&nbsp;</td>


</tr>

</tbody></table>
</div>
<!--[if gte mso 9]>
</v:textbox>
</v:rect>
<![endif]-->
</td>
</tr>

</tbody></table>
</td>
</tr>
<!-- article 1 -->
<tr>
<td class="void bg-l-blue mail-size" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-bottom: 0;padding-right: 0;padding-left: 0;font-size: 0;font-family: Helvetica, Arial, sans-serif !important;background-color: #ffffff;" width="800">
<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
<tbody><tr>
<td class="void bg-f2f mobile-hide" width="40" style="margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 0;padding-bottom: 0;font-family: Helvetica, Arial, sans-serif !important;padding-right: 0;padding-left: 0;font-size: 0;" bgcolor="#ffffff">
&nbsp;</td>

<td id="emailcontent" class="void bg-f2f mail-size mobile-p-20" width="520" style="margin: 0;padding: 15px 0px;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;margin-left: 0;padding-top: 15px;padding-right: 0;padding-left: 0;font-size: 14px;color:#2B3864" bgcolor="#ffffff">
EOD;

?>