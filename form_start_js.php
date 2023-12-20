<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <title>bot_1</title>
    
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    
    <link rel="stylesheet" href="./css/datatables.min.css">

    <script src="./js/jquery.js"></script>
    
    <script src="./js/bootstrap.min.js"></script>
    
    <script src="./js/datatables.min.js"></script>
    
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <style type="text/css">     
        html, body{
            height: 100%;
        }
        .full-page{
            height: 100vh;
            width: 100vw;
            }
    </style> 
</head>
<body>
    
    <?php
    // <div class="container">
    //     <div class="row">  
    //         <div id="result_wrap" class="col-12">
    //             <form action="config_upload.php" method="post" enctype="multipart/form-data" style="width:200px">
    //                 <div class="form-group">
    //                     <label>Выберите файл конфига</label>
    //                     <input type="file" class="form-control-file" name="config_file">
    //                 </div>
    //                 <button type="submit" name="config_upload" class="btn btn-primary">Анализ</button>
    //             </form>
    //         </div>
         
    //      </div>
    //  </div>
     ?>

    <section class="h-100">
        <header class="container h-100">
            <div id="cont" class="d-flex align-items-center justify-content-center h-100">
                <div class="d-flex flex-column">
                    <form id="form">
                        <div class="form-group">
                            <div>v4, js.</div>
                            <label>Выберите файл конфига:</label>
                            <input type="file" class="form-control-file" name="config_file" id="config_file">
                        </div>
                        <button type="submit" name="config_upload" class="btn btn-primary">Анализ</button>
                    </form>

                    <div id="status_count" class="row"></div>
                    <div id="status_symbols">

                    </div>
                </div>
            </div>
        </header>
    </section>

<script>
$status_script = 'work'
$("#form").on("submit", function(e){
    e.preventDefault();
    // console.log('submit')

    if (window.FormData === undefined) {
        alert('В вашем браузере FormData не поддерживается')
    } else {

        $("#form").css('display', 'none')
        $('#status_count').text('Прогресс: 0%');

        $('#cont').removeClass('h-100')
        
        setInterval(check_status, 5000);

        var formData = new FormData();
        formData.append('config_file', $("#config_file")[0].files[0]);

        $.ajax({
            type: "POST",
            url: 'config_upload_js.php',
            cache: false,
            contentType: false,
            processData: false,
            data: formData,
            // dataType : 'json',
            error: function(data){
                console.log('error')
                console.log(data)

                $('#status_count').text('Ошибка!');
                // $status_script = 'error'
            },
            success: function(data){
                console.log('success')
                if (data.type == 'error') {
                    console.log('error')
                    
                    $('#status_count').text('Ошибка! ' + data.msg);
                    $status_script = 'error'
                } else {
                    // $('#result').html(msg.error);
                    console.log('success')

                    

                    // $status_script = 'stop';
                }
                console.log(data.msg)
            }
        });
    }
});

function check_status(){

    if($status_script == 'work'){
        $.ajax({
            type: "GET",
            url: 'check_status.php',
            dataType : 'json',
            error: function(data){
                console.log('error')
                console.log(data)
            },
            success: function(data){
                // console.log('success')
                // console.log(data)
                
                

                if(data.count == 'end'){
                    $('#status_count').text('Прогресс: 100%');

                    $('#status_symbols').html("")
                    // выведем кнопку для ручного скачивания
                    $('#status_symbols').append("<div class='row'><a download id='href_result' href='result.csv' class='btn btn-success'>Скачать результат</a></div>")

                    $.each(data.symbols, function(key, value){
                        $('#status_symbols').append("<div class='row'>" + (key+1) + '. ' + value + "</div>")
                    });
                    // остановим отправку аякс запросов на проверку текущего статуса скрипта
                    $status_script = 'stop'

                    // дадим скачать файл автоматически
                    // window.location.href = 'result.csv'; //локально работает, на серваке почему то нет
                    var link = document.createElement('a');
                    link.setAttribute('href', 'result.csv');
                    link.setAttribute('download', 'result.csv');
                    link.click();


                }else{

                    $('#status_count').text('Прогресс: ' + data.count + '%');

                    $('#status_symbols').html("")
                    $('#status_symbols').append("<div class='row'><a download href='result.csv' class='btn btn-secondary'>Скачать текущий результат</a></div>")
                    $.each(data.symbols, function(key, value){
                        $('#status_symbols').append("<div class='row'>" + (key+1) + '. ' + value + "</div>")
                    });
                }
                
            }
        });
    }
}

</script>

 </body>
 </html>