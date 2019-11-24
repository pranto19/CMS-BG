<?php

return <<<'VALUE'
"namespace IPS\\Theme;\nclass class_gms_front_global extends \\IPS\\Theme\\Template\n{\n\tpublic $cache_key = 'f08b9663d84b3750506d9fdebffa0d5a';\n\tfunction messageList( $override=FALSE ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n\nCONTENT;\n\nif ( \\IPS\\Settings::i()->gms_enable OR $override == TRUE ):\n$return .= <<<CONTENT\n\n    \nCONTENT;\n\nif ( \\IPS\\Settings::i()->gms_include_global_title AND count( \\IPS\\gms\\Message::messages() ) AND $override == FALSE ):\n$return .= <<<CONTENT\n\n        <h3>\nCONTENT;\n\n$return .= htmlspecialchars( str_replace( '%board_name%', \\IPS\\Settings::i()->board_name, \\IPS\\Member::loggedIn()->language()->get( 'gms_title_value' ) ), ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h3>  \n    \nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n\n    \nCONTENT;\n\nforeach ( \\IPS\\gms\\Message::messages() as $message ):\n$return .= <<<CONTENT\n\n        \nCONTENT;\n\n$return .= \\IPS\\Theme::i()->getTemplate( \"global\", \"gms\" )->messageRow( $message );\n$return .= <<<CONTENT\n\n    \nCONTENT;\n\nendforeach;\n$return .= <<<CONTENT\n\n\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\nCONTENT;\n\n\t\treturn $return;\n}\n\n\tfunction messageRow( $message ) {\n\t\t$return = '';\n\t\t$return .= <<<CONTENT\n\n\nCONTENT;\n\nif ( $message->canSee() ):\n$return .= <<<CONTENT\n \n    \nCONTENT;\n\nif ( $options = json_decode( $message->options, TRUE ) AND ( $options['message_style'] == '1' OR $options['message_style'] == '2' )  ):\n$return .= <<<CONTENT\n\n        <div class='ipsMessage ipsMessage_info'>\n        \t\nCONTENT;\n\nif ( $message->show_title ):\n$return .= <<<CONTENT\n<h4 class='ipsMessage_title'>\nCONTENT;\n$return .= htmlspecialchars( $message->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h4>\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n        \t<p class='ipsType_reset ipsType_medium'>{$message->_description}<\/p>\n        <\/div>  \n    \nCONTENT;\n\nelseif ( $options['message_style'] == '3' ):\n$return .= <<<CONTENT\n\n        <div class='ipsMessage ipsMessage_error'>\n        \t\nCONTENT;\n\nif ( $message->show_title ):\n$return .= <<<CONTENT\n<h4 class='ipsMessage_title'>\nCONTENT;\n$return .= htmlspecialchars( $message->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h4>\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n        \t<p class='ipsType_reset ipsType_medium'>{$message->_description}<\/p>\n        <\/div>\n    \nCONTENT;\n\nelseif ( $options['message_style'] == '5' ):\n$return .= <<<CONTENT\n\n        <div class='ipsMessage ipsMessage_success'>\n        \t\nCONTENT;\n\nif ( $message->show_title ):\n$return .= <<<CONTENT\n<h4 class='ipsMessage_title'>\nCONTENT;\n$return .= htmlspecialchars( $message->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h4>\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n        \t<p class='ipsType_reset ipsType_medium'>{$message->_description}<\/p>\n        <\/div>  \n    \nCONTENT;\n\nelseif ( $options['message_style'] == '6' ):\n$return .= <<<CONTENT\n\n        <div class='ipsMessage ipsMessage_warning'>\n        \t\nCONTENT;\n\nif ( $message->show_title ):\n$return .= <<<CONTENT\n<h4 class='ipsMessage_title'>\nCONTENT;\n$return .= htmlspecialchars( $message->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/h4>\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n        \t<p class='ipsType_reset ipsType_medium'>{$message->_description}<\/p>\n        <\/div> \n    \nCONTENT;\n\nelseif ( !is_numeric( $options['message_style'] ) ):\n$return .= <<<CONTENT\n\n        <div class='\nCONTENT;\n$return .= htmlspecialchars( $options['message_style'], ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n'>\n        \t\nCONTENT;\n\nif ( $message->show_title ):\n$return .= <<<CONTENT\n<div id='gms_message_\nCONTENT;\n$return .= htmlspecialchars( $message->id, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n_title'>\nCONTENT;\n$return .= htmlspecialchars( $message->_title, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n<\/div>\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n        \t<div id='gms_message_\nCONTENT;\n$return .= htmlspecialchars( $message->id, ENT_QUOTES | \\IPS\\HTMLENTITIES, 'UTF-8', FALSE );\n$return .= <<<CONTENT\n_message'>{$message->_description}<\/div>\n        <\/div>         \n    \nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\n\nCONTENT;\n\nendif;\n$return .= <<<CONTENT\n\nCONTENT;\n\n\t\treturn $return;\n}}"
VALUE;