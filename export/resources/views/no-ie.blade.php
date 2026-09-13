<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Internet Explorer nicht unterstützt</title>
    <style>
        * {
            position: relative;
        }

        .no-ie {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 1002;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .no-ie-box {
            background-color: white;
            line-height: 1.4;
            width: 50%;
            padding: 30px;
        }

    </style>
</head>


<body>
    <div class="no-ie">
        <div class="no-ie-box">
            Der Browser &quot;Internet Explorer&quot; ist veraltet und wird von dieser Website nicht untersützt – wir empfehlen, die Website auf einer neuen Version von Firefox, Chrome oder Safari anzusehen.
        </div>
    </div>
</body>

</html>
