<?php
/**
 * 评论工具栏
 * 
 * @package CommentTools
 * @author 息E-敛
 * @version 0.1.0
 * @link http://tennsinn.com
 */
class CommentTools_Plugin implements Typecho_Plugin_Interface
{
	/**
	 * 激活插件方法,如果激活失败,直接抛出异常
	 * 
	 * @access public
	 * @return void
	 * @throws Typecho_Plugin_Exception
	 */
	public static function activate()
	{
		Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('CommentTools_Plugin', 'sendMail');
		Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = array('CommentTools_Plugin', 'sendMail');
		return _t('插件已开启，部分功能须设置后才能正常使用');
	}
	
	/**
	 * 禁用插件方法,如果禁用失败,直接抛出异常
	 * 
	 * @static
	 * @access public
	 * @return void
	 * @throws Typecho_Plugin_Exception
	 */
	public static function deactivate(){}
	
	/**
	 * 获取插件配置面板
	 * 
	 * @access public
	 * @param Typecho_Widget_Helper_Form $form 配置面板
	 * @return void
	 */
	public static function config(Typecho_Widget_Helper_Form $form)
	{
		$notice = new Typecho_Widget_Helper_Form_Element_Checkbox('notice',  array('admin' => _t('有新评论时，发邮件通知博主'), 'reviewer' => _t('有评论被回复时，发邮件通知评论者'), 'log' => _t('记录邮件发送日志')), NULL, _t('邮件发送设置'), _t('日志记录在phpmailer/log.txt文件中'));
		$form->addInput($notice->multiMode());

		$host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'smtp.', _t('SMTP地址'), _t('请填写SMTP服务器地址'));
		$form->addInput($host->addRule('required', _t('必须填写一个正确的SMTP服务器地址')));

		$ssl = new Typecho_Widget_Helper_Form_Element_Radio('ssl', array(0 => _t('关闭ssl加密'), 1 => _t('启用ssl加密')), 0, _t('ssl加密'), _t('选择是否开启ssl加密选项'));
		$form->addInput($ssl);

		$port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '25', _t('SMTP端口'), _t('SMTP服务端口，一般为25，ssl加密时一般为465'));
		$form->addInput($port->addRule('required', _t('必须填写相应的SMTP服务端口'))->addRule('isInteger', _t('端口号必须是纯数字')));

		$auth = new Typecho_Widget_Helper_Form_Element_Radio('auth', array(0 => _t('服务器无需验证'), 1 => _t('服务器需要验证')), 1, _t('服务器验证'), _t('选择服务器是否需要验证'));
		$form->addInput($auth);

		$username = new Typecho_Widget_Helper_Form_Element_Text('username', NULL, NULL, _t('SMTP用户名'),_t('SMTP服务验证用户名，一般为邮箱名如：youname@domain.com'));
		$form->addInput($username->addRule('required', _t('SMTP服务验证用户名')));

		$password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, NULL, _t('SMTP密码'));
		$form->addInput($password->addRule('required', _t('SMTP服务验证密码')));

		$address = new Typecho_Widget_Helper_Form_Element_Text('address', NULL, NULL, _t('博主接收邮箱'),_t('碎语发布者接收邮件用的信箱'));
		$form->addInput($address->addRule('required', _t('必须填写一个正确的接收邮箱'))->addRule('email', _t('请填写正确的邮箱！')));

		$titleAdmin = new Typecho_Widget_Helper_Form_Element_Text('titleAdmin', NULL, _t('{siteTitle}：「{articleTitle}」有了新的评论'), _t('博主接收的邮件标题'));
		$form->addInput($titleAdmin->addRule('required'), _t('必须填写一个有效的邮件标题'));

		$titileReviewer = new Typecho_Widget_Helper_Form_Element_Text('titileReviewer', NULL, _t('{siteTitle}：您在「{articleTitle}」的评论有了回复'), _t('评论者接收邮件标题'));
		$form->addInput($titileReviewer->addRule('required'), _t('必须填写一个有效的邮件标题'));
	}
	
	/**
	 * 个人用户的配置面板
	 * 
	 * @access public
	 * @param Typecho_Widget_Helper_Form $form
	 * @return void
	 */
	public static function personalConfig(Typecho_Widget_Helper_Form $form){}

	/**
	 * 评论邮件发送函数
	 * 
	 * @access public
	 * @param Widget_Abstract_Comments $comment
	 * @return void
	 */
	public static function sendMail($comment)
	{
		$settings = Helper::options()->plugin('CommentTools');
		if((in_array('admin', $settings->notice) && !$comment->authorId) || (in_array('reviewer', $settings->notice) && $comment->authorId))
		{
			$options = Helper::options();
			$dir = '.'. __TYPECHO_PLUGIN_DIR__.'/CommentTools/';
			require_once 'phpmailer/PHPMailerAutoload.php';
			$mail = new PHPMailer();
			$mail->CharSet = 'UTF-8';
			$mail->Encoding = 'base64';
			$mail->isSMTP();
			$mail->isHTML(true); 
			if($settings->auth)
				$mail->SMTPAuth = true;
			if($settings->ssl)
				$mail->SMTPSecure = 'ssl';
			$mail->Username = $settings->username;
			$mail->Password = $settings->password;
			$mail->Host = $settings->host;
			$mail->Port = $settings->port;
			$mail->setFrom($settings->username, $options->title);
			if($comment->parent)
			{
				$mail->addAddress($comment->mail, $comment->author);
				$commentParent = Typecho_Db::get()->fetchRow(Typecho_Db::get()->select()->from('table.comments')->where('coid = ?', $comment->parent)->limit(1));
				$subject = $settings->titileReviewer;
				$template = @file_get_contents($dir.'template/reviewer.html');
			}
			else
			{
				$mail->addAddress($settings->address);
				$subject = $settings->titleAdmin;
				$template = @file_get_contents($dir.'template/admin.html');
			}
			$search = array('{siteUrl}', '{siteTitle}', '{articleTitle}', '{coid}', '{permalink}', '{time}', '{author}', '{comment}', '{pcoid}', '{ppermalink}', '{ptime}', '{pcomment}');
			$replace = array($options->siteUrl, $options->title, $comment->title, $comment->coid, $comment->permalink, date('Y-m-d H:i', $comment->created+$options->timezone), $comment->author, $comment->text, $commentParent['coid'], $commentParent['permalink'], date('Y-m-d H:i', $commentParent['created']+$options->timezone), $commentParent['text']);
			$mail->Subject = str_replace($search, $replace, $subject);
			$mail->Body = str_replace($search, $replace, $template);
			$result = $mail->send();
			if(in_array('log', $settings->notice))
			{
				$fileLog = @fopen($dir.'phpmailer/log.txt','a+');
				$message = date('Y-m-d H:i', Typecho_Date::gmtTime()+$options->timezone).': ';
				if($result)
					$message .= '发送成功'."\r\n";
				else
					$message .= $mail->ErrorInfo."\r\n";
				fwrite($fileLog, $message);
				fclose($fileLog);
			}
		}
	}
}
