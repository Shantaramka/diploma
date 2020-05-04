<?php

  $bot_token = '[ДАННЫЕ УДАЛЕНЫ]';
  $link_students = '[ДАННЫЕ УДАЛЕНЫ]';
  $link_courses = '[ДАННЫЕ УДАЛЕНЫ]';

  $admin_id = '[ДАННЫЕ УДАЛЕНЫ]';

  function api($url, $method, $data, $json=false){
      $data=http_build_query($data);
      $method=mb_strtolower($method);
      if ($method<>'get' and $method<>'post')$method='get';
      if ($method=='get'){
        $curl=curl_init();
        curl_setopt($curl, CURLOPT_URL, $url.'/?'.$data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        $result = curl_exec($curl);
        curl_close($curl);
      }
      if ($method=='post'){
        $curl=curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        curl_close($curl);
      }
      if ($json)$result=json_decode($result, true);
      return $result;
    }

    function my_mb_ucfirst($str) {
      $fc = mb_strtoupper(mb_substr($str, 0, 1));
      return $fc.mb_substr($str, 1);
    }

  $body = json_decode(file_get_contents('php://input'), true);

  $message['user_id'] = $body['message']['from']['id'];
  $message['chat_id'] = $body['message']['chat']['id'];
  $message['text'] = $body['message']['text'];


  if ($message['text']=='/start'){
    // пользователь отправил /start
    api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Привет! Напиши свою фамилию']);
    file_put_contents('communication/'.$message['user_id'].'.txt', 'step1');
  }
  else {
    $step=trim(file_get_contents('communication/'.$message['user_id'].'.txt'));
    if ($step=='step1'){
      // ждём от пользователя Фамилию
      $message['text']=trim(my_mb_ucfirst($message['text'], "UTF-8", true));
      $student=[];
      if (($handle = fopen($link_students, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
          if ($data[0]==$message['text']){
            $student=$data;
          }
        }
        fclose($handle);
      }

      if ($student[0]!=''){
        // такой студень есть
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Привет, '.$student[1].'! Сегодня ты узнаешь про '.$student[2]]);
        $course=[];
        if (($handle = fopen($link_courses, "r")) !== FALSE) {
          while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if ($data[0]==$student[2]){
              $course=$data;
            }
          }
          fclose($handle);
        }
        file_put_contents('data/'.$message['user_id'].'.txt', $student[2]);
        file_put_contents('name/'.$message['user_id'].'.txt', $student[1].' '.$student[0]);
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Ссылка на презентацию - '.$course[1]]);
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Напиши что-нибудь, когда ознакомишься']);
        file_put_contents('communication/'.$message['user_id'].'.txt', 'step2');
      }else{
        // такого студента нет
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Нам нечему тебя учить!']);
        file_put_contents('communication/'.$message['user_id'].'.txt', 'step1');
      }

    }

    if ($step=='step2'){
      // Ждём, когда студент ознакомится с презентацией и напишет что-нибудь
      $student_course=file_get_contents('data/'.$message['user_id'].'.txt');
      $course=[];
      if (($handle = fopen($link_courses, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
          if ($data[0]==$student_course){
            $course=$data;
          }
        }
        fclose($handle);
      }

      api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Супер! Держи тест - '.$course[2]]);
      api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Как закончишь, пришли количество набранных баллов']);
      file_put_contents('communication/'.$message['user_id'].'.txt', 'step3');
    }

    if ($step=='step3'){
      // Ждём, когда студент пришлёт баллы
      $message['text'] = (int) $message['text'];
      $student_course=file_get_contents('data/'.$message['user_id'].'.txt');
      $course=[];
      if (($handle = fopen($link_courses, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
          if ($data[0]==$student_course){
            $course=$data;
          }
        }
        fclose($handle);
      }

      $points=(int) $course[3];

      if ($message['text']>=$points){
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Супер! Ты успешно закончил курс '.$course[0]]);
        $student_name=file_get_contents('name/'.$message['user_id'].'.txt');
        $course_name=file_get_contents('data/'.$message['user_id'].'.txt');
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$admin_id, 'text'=>$student_name.' закончил курс '.$course_name.'. Количество баллов - '.$message['text']]);
        unlink('communication/'.$message['user_id'].'.txt');
        unlink('data/'.$message['user_id'].'.txt');
        unlink('name/'.$message['user_id'].'.txt');
      }else{
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Плохо! Слишком мало баллов, чтобы закончить курс '.$course[0]]);
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Держи презентацию ещё раз - '.$course[1]]);
        api('https://api.telegram.org/bot'.$bot_token.'/sendMessage', 'post', ['chat_id'=>$message['chat_id'], 'text'=>'Напиши что-нибудь, когда ознакомишься']);
        file_put_contents('communication/'.$message['user_id'].'.txt', 'step2');
      }
    }

  }


?>
