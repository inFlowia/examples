<?php
/*
Точка входа - Контент
	- получение ИД-а контента из УРЛ;
	- получение контента из БД (через внешний модуль);
	- вывод общей части страницы;
	- вывод элементов управления если авторизован;
	- вывод контента при помощи соотв. шаблона (шаблоны - внешние модули);

Вся общая часть для шаблонов внесена в эту программу, в шаблонах только уникальное.

После переименования этого каталога нужно поправить:
	.htaccess в этом каталоге, строку RewriteRule ^(.*)$ /имя_каталога/index.php [L,QSA]
	константу PATH_TO_CONTENT_ENTRY
*/
require_once $_SERVER['DOCUMENT_ROOT'].'/hatHeader.php';

require_once doc_root().'/lib/for-admin/for-admin.php'; // add_edit_btn() нельзя подключать условно так как функция будет не определена если не авторизован.
require_once doc_root().'/a/lib.php';
if(isAuth())
	echo "
		<script src = '/lib/for-admin/for-admin.js'></script>
		<script async src = '".from_this(__DIR__)."content-for-admin.js'></script>
	"; // для обработчика кнопки удаления

define('MSG_NO_CONTENT', "<div class = 'bad_news'>Такого на инФловии нет.
	<p>И откуда вы только эти ссылки битые берёте...</p></div>"); // Конкретное сообщение об ошибке. Выводится в первую очередь
define('MSG_NO_CONTENT_GENERAL', "<div class = 'bad_news'>Что-то пошло не так ':(</div>"); // Общее сообщение об ошибке пользователю. выводится в последнюю очередь.

try{ // получение ИД контента из УРЛ + запросы к БД
	$urlParts = explode('/', trim($_SERVER['REQUEST_URI'], '/')); // получаем массив частей url, trim удалят начальные и конечные разделители, чтобы не было лишних элементов массива
	switch (count($urlParts)){
		case 1: // перешли просто в директорию - редир. на гл. (потом можно что-то придумать поумнее)
			echo '<meta http-equiv="refresh" content="0;URL=http://'.$_SERVER['HTTP_HOST'].'/">';
			die;
		case 2: // ид. контента передан
			$name_4_url	= $urlParts[1];
			if(!isset($name_4_url) || ($name_4_url == ''))
		 		throw new ExcM('очень странная ошибка: name_4_url было найдено в URL, но после получения его из URL, переменная $name_4_url оказалась либо пустой строкой либо не задана', MSG_NO_CONTENT);
			else{ // всё ок
				try{
					require_once $_SERVER['DOCUMENT_ROOT'].'/lib/DB/get-content.php';
					require_once $_SERVER['DOCUMENT_ROOT'].'/lib/DB/get-tags.php';
				}
				catch(Exception $exc){throw new ExcM('ошибка при получении контента', MSG_NO_CONTENT, $exc);}
			}
			break;
		default:
			throw new ExcM('Слишком много сегментов URL. На данный момент для контента из БД поддерживаются только односегментные имена для URL. Контент, хранящийся на страницах не должен обрабатываться при помощи этой программы.', MSG_NO_CONTENT);
		}
	} // try получение ИД контента из УРЛ + запросы к БД
catch(Exception $exc){
	$excM = new ExcM('ошибка при получении данных', MSG_NO_CONTENT_GENERAL, $exc);
	$err_msgs = $excM->get_fmsgs();
}

if(!empty($err_msgs)){
	$title = 'Загадочная страница inFlowia Lab.';
	$desc = 'Тут скорее всего какая-то ошибка';
	require_once $_SERVER['DOCUMENT_ROOT'].'/hatBody.php'; // нельзя подключать раньше получения контента, так как здесь выводятся заголовки
	echo $err_msgs;
}
else{ // [ВЫВОД ШАБЛОНА ЗДЕСЬ] если нет ошибок с получением контента

	/* [ВЫВОД ШАБЛОНА ЗДЕСЬ] Задаёт title и desсription, закрывает header, выводит начало страницы
	Вспомогательная, для повторного исп. кода.
	*/
	function show_body_beg(){
		global $rslt;
		$title = $rslt['header'];
		$desc = $rslt['descr'];
		require_once $_SERVER['DOCUMENT_ROOT'].'/hatBody.php'; // нельзя подключать раньше получения контента, так как здесь выводятся заголовки
		require_once __DIR__.'/content-js.php';
	}

	/* [ВЫВОД ШАБЛОНА ЗДЕСЬ] Выводит контент в соответствующем шаблоне.
	Вспомогательная, для повторного исп. кода.
	*/
	function show_content_in_template(){
		global $rslt, $tags, $name_4_url;

		echo "<header>
					<h1>{$rslt['header']}</h1>";
		switch($rslt['template']){
			case 0:
				require_once './templates/blank.php';
				break;
			case 1:
				require_once './templates/static.php';
				break;
			case 2:
				require_once './templates/dynamic.php';
				break;
			case 3:
				require_once './templates/script.php';
		} // switch template
		if(isset($tags)){ // вывод тегов
			echo "<nav class = 'tags'>";
			foreach($tags as $tag){
				$name = ($tag['name_short']) != '' ? $tag['name_short'] : $tag['name'];
				echo "<a target = '_blank' href = '/tag/{$tag['name_4_url']}'>$name</a> ";
			}
			echo "</nav>";
		} // if isset tags
		add_edit_btn($name_4_url);
		add_del_btn($name_4_url);
		require doc_root().'/common/tell-thank.php';
	} // function show_content_in_template

	switch($rslt['access']){ // обработка статуса опубликованности контента
		case 0: // скрыто
			if(!isAuth()){ // не авторизованным
				$title = 'Загадочная страница inFlowia Lab.';
				$desc = 'Тут скорее всего какая-то ошибка';
				require_once $_SERVER['DOCUMENT_ROOT'].'/hatBody.php'; // нельзя подключать раньше получения контента, так как здесь выводятся заголовки
				// имитация отсутствия контента. Не позволит любопытным понять, что они наткнулись на скрытую статью. Это проблемный код, так как он требует поддержания актуальности.
				$exc1 = new ExcM('', MSG_NO_CONTENT);
				$exc2 = new ExcM('', MSG_NO_CONTENT_GENERAL, $exc1);
				$exc2->show();
			}
			else{ // для авторизованных
				show_body_beg();
				// Подкрашиваем неопубликованные и выводим уведомление
				echo "
					<script>
						$(document).ready(function (){
							$('body').addClass('unPubl');
							$('h1').after('<div class = \'wrn\'>Не опубликовано!</div>');
						});
					</script>
				";
				show_content_in_template();
			}
			break;
		case 1: //опубликовано
			show_body_beg();
			show_content_in_template();
	} // обработка статуса опубликованности контента

} // если нет ошибок с получением контента

require_once $_SERVER['DOCUMENT_ROOT'].'/footer.php';
?>
