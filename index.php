<?php

/*
|--------------------------------------------------------------------------
| Shim cho XAMPP / shared hosting
|--------------------------------------------------------------------------
|
| Khi dùng XAMPP, DocumentRoot thường trỏ tới thư mục gốc dự án thay vì
| thư mục `public/`. File này chuyển tiếp request vào `public/index.php`
| để ứng dụng hoạt động đúng như bình thường.
|
| Với production thực sự, DocumentRoot NÊN trỏ thẳng vào `public/`.
|
*/

require __DIR__.'/public/index.php';
