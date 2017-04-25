<?php
$title=($cfg && is_object($cfg) && $cfg->getTitle())
    ? $cfg->getTitle() : 'osTicket :: '.__('Support Ticket System');
$signin_url = ROOT_PATH . "login.php"
    . ($thisclient ? "?e=".urlencode($thisclient->getEmail()) : "");
$signout_url = ROOT_PATH . "logout.php?auth=".$ost->getLinkToken();

header("Content-Type: text/html; charset=UTF-8");
if (($lang = Internationalization::getCurrentLanguage())) {
    $langs = array_unique(array($lang, $cfg->getPrimaryLanguage()));
    $langs = Internationalization::rfc1766($langs);
    header("Content-Language: ".implode(', ', $langs));
}
?>
<!DOCTYPE html>
<html<?php
if ($lang
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . $lang . '"';
}
?>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title><?php echo Format::htmlchars($title); ?></title>
    <meta name="description" content="customer support platform">
    <meta name="keywords" content="osTicket, Customer support system, support ticket system">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/osticket.css?901e5ea" media="screen"/>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/theme.css?901e5ea" media="screen"/>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/print.css?901e5ea" media="print"/>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>scp/css/typeahead.css?901e5ea"
         media="screen" />
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.10.3.custom.min.css?901e5ea"
        rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/thread.css?901e5ea" media="screen"/>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css?901e5ea" media="screen"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css?901e5ea"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/flags.css?901e5ea"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css?901e5ea"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css?901e5ea"/>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-1.11.2.min.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.10.3.custom.min.js?901e5ea"></script>
    <script src="<?php echo ROOT_PATH; ?>js/osticket.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/filedrop.field.js?901e5ea"></script>
    <script src="<?php echo ROOT_PATH; ?>scp/js/bootstrap-typeahead.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor.min.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-plugins.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-osticket.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/select2.min.js?901e5ea"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/fabric.min.js?901e5ea"></script>
    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }

    // Offer alternate links for search engines
    // @see https://support.google.com/webmasters/answer/189077?hl=en
    if (($all_langs = Internationalization::getConfiguredSystemLanguages())
        && (count($all_langs) > 1)
    ) {
        $langs = Internationalization::rfc1766(array_keys($all_langs));
        $qs = array();
        parse_str($_SERVER['QUERY_STRING'], $qs);
        foreach ($langs as $L) {
            $qs['lang'] = $L; ?>
        <link rel="alternate" href="//<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>?<?php
            echo http_build_query($qs); ?>" hreflang="<?php echo $L; ?>" />
<?php
        } ?>
        <link rel="alternate" href="//<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>"
            hreflang="x-default" />
<?php
    }
    ?>
</head>
<body>

        
<div id="content">

<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');

$email=Format::input($_POST['luser']?:$_GET['e']);
$passwd=Format::input($_POST['lpasswd']?:$_GET['t']);

$content = Page::lookupByType('banner-client');

if ($content) {
    list($title, $body) = $ost->replaceTemplateVariables(
        array($content->getName(), $content->getBody()));
} else {
    $title = __('Sign In');
    $body = __('To better serve you, we encourage our clients to register for an account and verify the email address we have on record.');
}

?>

<div id="loginSection">
    <form action="login.php" method="post" id="clientLogin">
        
        <a href="<?php echo ROOT_PATH; ?>login.php" title="<?php echo __('Support Center'); ?>">
            <img id="logoLogin" src="<?php echo ROOT_PATH; ?>logo.php" border=0 alt="<?php
            echo $ost->getConfig()->getTitle(); ?>">
        </a>
        
        <div id="agentLogin">
            <b><?php echo __("I'm an agent"); ?></b> —
            <a href="<?php echo ROOT_PATH; ?>scp/"><?php echo __('sign in here'); ?></a>
        </div>
        <div id="loginMenu">
            <h1><?php echo Format::display($title); ?></h1>
            <ul>
                <a href="<?php echo ROOT_PATH; ?>/index.php"><li><img class="iconHome" src="<?php echo ROOT_PATH; ?>/images/icons/Home-48.png" width="15" />Support Center</li></a>
                <?php if ($cfg && $cfg->isKnowledgebaseEnabled()): ?>
                    <a href="<?php echo ROOT_PATH; ?>/kb/index.php"><li><img class="iconKB" src="<?php echo ROOT_PATH; ?>/images/icons/Literature-26.png" width="15" />KnowledgeBase</li></a>
                <?php endif; ?>
            </ul>
        </div>
        
        <div id="clientLoginContent">
            
            <p><?php echo Format::display($body); ?></p>
                <?php csrf_token(); ?>
            <div>
                <div class="login-box">
                    
                <?php if($errors['err']) { ?>
                    <div id="msg_error"><?php echo $errors['err']; ?></div>
                <?php }elseif($msg) { ?>
                    <div id="msg_notice"><?php echo $msg; ?></div>
                <?php }elseif($warn) { ?>
                    <div id="msg_warning"><?php echo $warn; ?></div>
                <?php } ?>
                    
                <strong><?php echo Format::htmlchars($errors['login']); ?></strong>
                <div>
                    <input id="username" placeholder="<?php echo __('Email or Username'); ?>" type="text" name="luser" size="30" value="<?php echo $email; ?>" class="nowarn">
                </div>
                <div>
                    <input id="passwd" placeholder="<?php echo __('Password'); ?>" type="password" name="lpasswd" size="30" value="<?php echo $passwd; ?>" class="nowarn"/>
                </div>
                <p>
                    <input class="btn" type="submit" value="<?php echo __('Sign In'); ?>">
            <?php if ($suggest_pwreset) { ?>
                    <a style="padding-top:4px;display:inline-block;" href="pwreset.php"><?php echo __('Forgot My Password'); ?></a>
            <?php } ?>
                </p>
                <hr/>

                  <?php

            $ext_bks = array();
            foreach (UserAuthenticationBackend::allRegistered() as $bk)
                if ($bk instanceof ExternalAuthentication)
                    $ext_bks[] = $bk;

            if (count($ext_bks)) {
                foreach ($ext_bks as $bk) { ?>
            <div class="external-auth"><?php $bk->renderExternalLink(); ?></div><?php
                }
            }
            if ($cfg && $cfg->isClientRegistrationEnabled()) {
                if (count($ext_bks)) echo '<hr style="width:70%"/>'; ?>
                <div style="margin-bottom: 5px">
                
                    <center>
                        <div class="attachedBtns">
                            <div class="attachedBtn navy noselect" id="signRegBtn"><img class="iconLock" src="<?php echo ROOT_PATH; ?>/images/icons/Lock-26.png" width="15"/>Sign in with DNV GL Home</div>
                            <div class="attachedBtnCircle"></div>
                            <div class="attachedBtn" id="signRegContact">Contact IT Dept</div>
                        </div>
                    </center>
                
                </div>
            <?php } ?>
                
                </div>
                
            </div>
        
        </div>
        <div id="clientLoginFooter">
            <p>Copyright &copy; <?php echo date('Y'); ?> <?php echo (string) $ost->company ?: 'osTicket.com'; ?> - All rights reserved.</p>
        </div>
    </form>

</div>

<script type="text/javascript">
    $(document).ready(function() {
        var winHeight = $(document).height();
        var winHeight2 = $(document).height() - 200;
        
        $("#loginSection").css("height", winHeight);
        $("#clientLoginContent").css("height", winHeight2);
        
        $(window).resize(function() {
            var winHeightResp = $(document).height();
            var winHeightResp2 = $(document).height() - 200;
            
            $("#loginSection").css("height", winHeightResp);
            $("#clientLoginContent").css("height", winHeightResp2);
        });
        
    });
</script>