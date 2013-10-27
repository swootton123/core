<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2008
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 *
 * $Id$
 */

if ( !defined('EQDKP_INC') ){
	header('HTTP/1.0 404 Not Found');exit;
}

if (!class_exists("comments")){
	class comments extends gen_class {
		public static $shortcuts = array('user', 'tpl', 'pdh', 'time', 'in', 'pfh', 'routing', 'hooks',
			'bbcode'	=> 'bbcode',
		);

		public $count = array();
		public $userPerm = true;
		public $isAdmin = false;
		public $showReplies = false;
		private $id = '';
		private $showFormForGuests=false;

		// ---------------------------------------------------------
		// Constructor
		// ---------------------------------------------------------
		public function __construct($id=''){
			//ID for multiple instances on one page
			$this->id = $id;
			
			$this->UserID		= (isset($this->user->data['user_id']) && $this->user->is_signedin()) ? $this->user->data['user_id'] : false;
			$this->version		= '2.0.0';

			// Changeable
			$this->isAdmin		= $this->user->check_auth('a_config_man', false); //TODO: check for a specific group-membership
		}

		// ---------------------------------------------------------
		// Set the Comment Variables during runtime..
		// ---------------------------------------------------------
		public function SetVars($array){
			if(isset($array['auth'])){
				$this->isAdmin		= $this->user->check_auth($array['auth'], false);
			}
			if(isset($array['userauth'])){
				$this->userPerm		= $this->user->check_auth($array['userauth'], false);
			}
			if(isset($array['page'])){
				$this->page			= $array['page'];
			}
			if(isset($array['attach_id'])){
				$this->attach_id	= $array['attach_id'];
			}
			if(isset($array['replies'])){
				$this->showReplies	= $array['replies'];
			}
			if(isset($array['formforguests'])){
				$this->showFormForGuests = $array['formforguests'];
			}
		}

		// ---------------------------------------------------------
		// Get the Count of comments for that event/page
		// ---------------------------------------------------------
		public function Count(){
			return $this->pdh->get('comment', 'count', array($this->page, $this->attach_id));
		}

		// ---------------------------------------------------------
		// Save the Comment
		// ---------------------------------------------------------
		public function Save(){
			$data = array(
				'user_id' 	=> $this->UserID,
				'attach_id' => 	$this->in->get('attach_id'),
				'comment'	=> $this->in->get('comment', '', 'htmlescape'),
				'page'		=> $this->in->get('page'),
				'reply_to'	=>  $this->in->get('reply_to', 0),
				'permission'=> ($this->UserID && $this->userPerm),
			);
			
			//Hooks
			$data = $this->hooks->process('comments_save', $data, true);
			
			if($data['permission']){
				$this->pdh->put('comment', 'insert', array($data['attach_id'], $data['user_id'], $data['comment'], $data['page'], $data['reply_to']));
				$this->pdh->process_hook_queue();
				echo $this->Content($data['attach_id'], $data['page'], ($data['reply_to'] || $this->in->get('replies', 0)));
			}
		}

		// ---------------------------------------------------------
		// Delete the Comment
		// ---------------------------------------------------------
		public function Delete($page, $blnShowReplies=false){
			if($this->isAdmin || $this->pdh->get('comment', 'userid', array($this->in->get('deleteid', 0))) == $this->UserID){
				$this->pdh->put('comment', 'delete', array($this->in->get('deleteid',0)));
				$this->pdh->process_hook_queue();
				echo $this->Content($this->in->get('attach_id',''), $this->in->get('page'), $blnShowReplies);
			}
		}

		// ---------------------------------------------------------
		// HTML Output Code
		// ---------------------------------------------------------
		public function Show(){
			$this->JScode();
			$html	= '<div id="plusComments'.$this->id.'"><div id="htmlCommentTable'.$this->id.'">';
			$html	.= $this->Content($this->attach_id, $this->page);
			$html	.= '</div>';
			$html .= $this->ReplyForm($this->attach_id, $this->page);

			// the line for the comment to be posted
			if(($this->user->is_signedin() && $this->userPerm) || $this->showFormForGuests){
				$html .= $this->Form($this->attach_id, $this->page);
			}
			$html .= '</div>';
			return $html;
		}

		// ---------------------------------------------------------
		// Generate the Content
		// ---------------------------------------------------------
		public function Content($attachid, $page, $blnShowReplies=false){
			$i				= 0;
			$comments		= $this->pdh->get('comment', 'filtered_list', array($page, $attachid));
			$myrootpath		= $this->server_path;
			$this->bbcode->SetSmiliePath($myrootpath.'images/smilies');
			// The delete form
			$html	= '<form id="comment_delete" name="comment_delete" action="'.$this->server_path.'exchange.php'.$this->SID.'&amp;out=comments" method="post">';
			$html	.= '</form>';

			// the content Box
			$html	.= '<div class="contentBox">';
			$html	.= '<div class="boxHeader"><h1>'.$this->user->lang('comments').'</h1></div>';
			$html	.= '<div class="boxContent">';

			$out = '';
			if (is_array($comments)){
				foreach($comments as $row){
					// Avatar
					$avatarimg = $this->pdh->get('user', 'avatarimglink', array($row['userid']));

					// output
					$out[] .= '<div class="comment '.(($i%2) ? 'rowcolor2' : 'rowcolor1').' clearfix">
								<div class="comment_id" style="display:none;">'.$row['id'].'</div>
								<div class="comment_avatar_container">
									<div class="comment_avatar"><a href="'.$this->routing->build('user', $row['username'], 'u'.$row['userid']).'"><img src="'.(($avatarimg) ? $this->pfh->FileLink($avatarimg, false, 'absolute') : $myrootpath.'images/no_pic.png').'" alt="Avatar" class="user-avatar"/></a></div>
								</div>
								<div class="comment_container">
									<div class="comment_author"><a href="'.$this->routing->build('user', $row['username'], 'u'.$row['userid']).'">'.sanitize($row['username']).'</a> am '.$this->time->user_date($row['date'], true).'</div>';
					if($this->isAdmin OR $row['userid'] == $this->UserID){
						$out[] .= '<div class="comments_delete bold floatRight hand"><i class="fa fa-times-circle fa-lg icon-grey"></i>';
						$out[] .= '<div style="display:none" class="comments_page">'.$page.'</div>';
						$out[] .= '<div style="display:none" class="comments_deleteid">'.$row['id'].'</div>';
						$out[] .= '<div style="display:none" class="comments_attachid">'.$attachid.'</div>';
						$out[] .= '<div style="display:none" class="comments_myrootpath">'.$myrootpath.'</div>';
						$out[] .= '</div>';
					}
					$out[] .= '<div class="comment_text">'.$this->bbcode->MyEmoticons($this->bbcode->toHTML($row['text'])).'</div><br/>
								</div>';
								
								
					$i++;
					
					//Replies
					if (($this->showReplies || $blnShowReplies) && count($row['replies'])) {
						$j=0;
						foreach($row['replies'] as $com){
							// Avatar
							$avatarimg = $this->pdh->get('user', 'avatarimglink', array($com['userid']));

							// output
							$out[] .= '<div class="clear"></div><br/><div class="comment-reply '.(($j%2) ? 'rowcolor2' : 'rowcolor1').' clearfix">
										<div class="comment_id" style="display:none;">'.$com['id'].'</div>
										<div class="comment_avatar_container">
											<div class="comment_avatar"><a href="'.$this->routing->build('user', $com['username'], 'u'.$com['userid']).'"><img src="'.(($avatarimg) ? $this->pfh->FileLink($avatarimg, false, 'absolute') : $myrootpath.'images/no_pic.png').'" alt="Avatar" class="user-avatar"/></a></div>
										</div>
										<div class="comment_container">
											<div class="comment_author"><a href="'.$this->routing->build('user', $com['username'], 'u'.$com['userid']).'">'.sanitize($com['username']).'</a> am '.$this->time->user_date($com['date'], true).'</div>';
							if($this->isAdmin OR $com['userid'] == $this->UserID){
								$out[] .= '<div class="comments_delete bold floatRight hand"><i class="fa fa-times-circle fa-lg icon-grey"></i>';
								$out[] .= '<div style="display:none" class="comments_page">'.$page.'</div>';
								$out[] .= '<div style="display:none" class="comments_deleteid">'.$com['id'].'</div>';
								$out[] .= '<div style="display:none" class="comments_attachid">'.$attachid.'</div>';
								$out[] .= '<div style="display:none" class="comments_myrootpath">'.$myrootpath.'</div>';
								$out[] .= '</div>';
							}
							$out[] .= '<div class="comment_text">'.$this->bbcode->MyEmoticons($this->bbcode->toHTML($com['text'])).'</div><br/>
										</div>
										</div>
										
										';
							$j++;
						}
					}
					if (($this->showReplies || $blnShowReplies) && $this->user->is_signedin()){
						$out[] .= '<div class="comment_reply_container">
										<button class="reply-trigger"><i class="fa fa-reply"></i>'.$this->user->lang('reply').'</button>
										<div class="reply-form-container">
										</div>
									</div>';
					}
								
					$out[] .='	</div>
								
								<br/>';
				}
			}

			if(isset($out) && is_array($out) && count($out) > 0){
				foreach($out as $vvalues){
					$html .= $vvalues;
				}
			}else{
				$html .= $this->user->lang('comments_empty');
			}
			$html .= '</div></div>';
			return $html;
		}

		// ---------------------------------------------------------
		// Private Functions
		// ---------------------------------------------------------
		private function Form($attachid, $page){
			$editor = registry::register('tinyMCE');
			$editor->editor_bbcode();
			$avatarimg = $this->pdh->get('user', 'avatarimglink', array($this->user->id));
			$html = '<div class="contentBox writeComments">';
			$html .= '<div class="boxHeader"><h1>'.$this->user->lang('comments_write').'</h1></div>';
			$html .= '<div class="boxContent"><br/>';
			$html .= '<form id="comment_data'.$this->id.'" name="comment_data" action="'.$this->server_path.'exchange.php'.$this->SID.'&amp;out=comments&replies='.(($this->showReplies) ? 1 : 0).'" method="post">
						<input type="hidden" name="attach_id" value="'.$attachid.'"/>
						<input type="hidden" name="page" value="'.$page.'"/>
						<div class="clearfix">
							<div class="comment_avatar_container">
								<div class="comment_avatar"><a href="'.$this->routing->build('user', $this->user->data['username'], 'u'.$this->user->id).'"><img src="'.(($avatarimg) ? $this->pfh->FileLink($avatarimg, false, 'absolute') : $this->server_path.'images/no_pic.png').'" alt="Avatar" class="user-avatar"/></a></div>
							</div>
							<div class="comment_write_container">
								<textarea name="comment" rows="5" cols="80" class="mceEditor_bbcode" style="width:100%;"></textarea>
							</div>
						</div>
						<span id="comment_button'.$this->id.'"><input type="submit" value="'.$this->user->lang('comments_send_bttn').'" class="input"/></span>
					</form>';
			$html .= '</div></div>';
			
			
			
			return $html;
		}
		
		private function ReplyForm($attachid, $page){
			$editor = registry::register('tinyMCE');
			$editor->editor_bbcode();
			$avatarimg = $this->pdh->get('user', 'avatarimglink', array($this->user->id));
			
			$html = '<div class="commentReplyForm" style="display:none;">
					<form class="comment_reply" action="'.$this->server_path.'exchange.php'.$this->SID.'&amp;out=comments" method="post">
						<input type="hidden" name="attach_id" value="'.$attachid.'"/>
						<input type="hidden" name="page" value="'.$page.'"/>
						<input type="hidden" name="reply_to" value="0"/>
						<div class="clearfix">
							<div class="comment_avatar_container">
								<div class="comment_avatar"><a href="'.$this->routing->build('user', $this->user->data['username'], 'u'.$this->user->id).'"><img src="'.(($avatarimg) ? $this->pfh->FileLink($avatarimg, false, 'absolute') : $this->server_path.'images/no_pic.png').'" alt="Avatar" class="user-avatar"/></a></div>
							</div>
							<div class="comment_write_container">
								<textarea name="comment" rows="2" cols="80" class="" style="width:100%;"></textarea>
							</div>
							<span class="reply_button"><input type="submit" value="'.$this->user->lang('comments_send_bttn').'" class="input"/></span>
						</div>
					</form>
				</div>';
			return $html;
		}

		// ---------------------------------------------------------
		// Generate the JS Code
		// ---------------------------------------------------------
		private function JScode(){
			$jscode = "
						// Delete Function
						$(document).on('click', '#plusComments".$this->id." .comments_delete', function(){
							var page			= $('.comments_page',		this).text();
							var deleteid		= $('.comments_deleteid',	this).text();
							var attachid		= $('.comments_attachid',	this).text();

							$('#comment_delete').ajaxSubmit({
								target: '#htmlCommentTable".$this->id."',
								url:	'".$this->server_path."exchange.php".$this->SID."&out=comments&deleteid='+deleteid+'&page='+page+'&attach_id='+attachid+'&replies=".(($this->showReplies) ? 1 : 0)."',
								success: function() {
									$('#htmlCommentTable".$this->id."').fadeIn('slow');
								}
							});
						});
												
						//Show Reply Form
						$(document).on('click', '#plusComments".$this->id." .reply-trigger', function(){
							var reply_to = $(this).parent().parent().find('.comment_id:first').text();
							console.log(reply_to);
							var newform = $('#plusComments".$this->id." .commentReplyForm').html();
							$('#plusComments".$this->id." .reply-trigger').show();
							$('#plusComments".$this->id." .form-active').remove();
							$(this).hide('fast');
							var container = $(this).parent().find('.reply-form-container');						
							$(container).html(newform);
							$(container).find('.comment_reply').addClass('form-active');					
							var myform = $(container).find('.comment_reply');
							$(myform).find('textarea').addClass('mceEditor_bbcode');
							$(myform).attr('id', 'comment_reply".$this->id."');
							$(myform).find('input[name=reply_to]').val(reply_to);
							
							initialize_bbcode_editor();
							initialize_submit_reply".$this->id."();
						});
									
						//Submit Reply
						function initialize_submit_reply".$this->id."(){
							$('#comment_reply".$this->id."').ajaxForm({
								target: '#htmlCommentTable".$this->id."',
								beforeSubmit:  function(){
									$('#plusComments".$this->id." .reply_button').html('<i class=\"fa fa-spinner fa-spin fa-lg\"></i> ".$this->user->lang('comments_savewait')."');
								},
								success: function() {
									$('#htmlCommentTable".$this->id."').fadeIn('slow');
									// clear the input field:
									$('#plusComments".$this->id." .reply_button').html('<input type=\"submit\" value=\"".$this->user->lang('comments_send_bttn')."\" class=\"input\"/>');
								}
							});
						}
											
						// submit Comment
						$('#comment_data".$this->id."').ajaxForm({
							target: '#htmlCommentTable".$this->id."',
							beforeSubmit:  function(){
								$('#comment_button".$this->id."').html('<i class=\"fa fa-spinner fa-spin fa-lg\"></i> ".$this->user->lang('comments_savewait')."');
							},
							success: function() {
								$('#htmlCommentTable".$this->id."').fadeIn('slow');
								// clear the input field:
								$(\".mceEditor_bbcode\").val('');
								$(\".mceEditor_bbcode\").tinymce().setContent('');
								$('#comment_button".$this->id."').html('<input type=\"submit\" value=\"".$this->user->lang('comments_send_bttn')."\" class=\"input\"/>');
							}
						});
						
						reload_comments".$this->id."();
						";
			$this->tpl->add_js($jscode, 'docready');
			
			$jscode =	"//Reload comments
						function reload_comments".$this->id."(){
							var form = $('#comment_data".$this->id."');
							var page = form.find(\"input[name='page']\").val();
							var attach_id = form.find(\"input[name='attach_id']\").val();
							window.setTimeout(\"reload_comments".$this->id."()\", 60000*5); // 5 Minute
							
							$.ajax({
							url: '".$this->server_path."exchange.php".$this->SID."&out=comments&page='+page+'&attach_id='+attach_id+'&replies=".(($this->showReplies) ? 1 : 0)."',
								success: function(data){ $('#htmlCommentTable".$this->id."').html(data);},
							});					
						}
						window.setTimeout(\"reload_comments".$this->id."()\", 60000*5); // 5 Minute
						";
			$this->tpl->add_js($jscode, 'eop');				

		}
	}
}
?>