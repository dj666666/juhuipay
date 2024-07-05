<?php

return [

    'alipay_config' => [
        //支付宝 支付配置
        'gatewayUrl'            => 'https://openapi.alipay.com/gateway.do',//支付宝网关（固定)'
        'appId'                 => '2021003181614873',//APPID即创建应用后生成
        //由开发者自己生成: 请填写开发者私钥去头去尾去回车，一行字符串
        'rsaPrivateKey'         =>  'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCx2ZMoBnAOt0yPDh1tNSy16y/Dm3wabDKdLsyLbGwlrYL7ndI8nAzlkVJIlzslwv9MYGCJk7jgDKID38uLeOqqjoFyrNzr40Q6jcmdcan8ueVzOBGzNjgZZX3gkcCkoeMpHYcHZfDskFsmrae/J0S2cVUsiVyC/C1nrZcRLmn2Sr5eZbQzCREOuNBzxOFK2v2C7MfUJmz6aOWPJbRk7gFP9QCyYmTb5yqjqfRa9WLTJu67qTP3ombmaIGHTUZ+6+UP3M0o9BAcXjfyNQ94D4mtCWfFGen+aSuNx3EF2JDRNjQrvmBWtJBKh/J3ciCk0SWcvsRNU29/GhgBFCu1Zck9AgMBAAECggEAayu1JXVbqUKDe+EBkoFsg+NJURIs4q84gMdmss2PDdVVDNK5kZRnoR7E+sFG/yZWOWwKQF0oFrsGFleRjGY84rIlBzlrlynIP5CZYarQyF4tChVLdTbC72rdQ6oQ8CQtguUsLSUc3TDP/KrAXswG9/mrXb8YZEYaBlPwqIXTjHnUOrWXwFzovIxTt0ytrQaJCmNJgaxf63SY9qYHeCiWG+nUu1NO97k7BXLGwTS43/bIoPHWd+NuKcMocb3kGWxf3ijHvj0KE1ssMfkQEScXOaHSgyh2Sa6Gk43dQQkohUVBQirA76Q/dKF4LgaFpdePRuAF9SYyVQPbPdgu//Eu8QKBgQDjLz9eEwliFQnpKzkCc35B/A0CcoJ405b19CgKny5W6+UTd62KkZ9NZPofx0Jpib0uZAHZtCMZxsNLbv8o+wKz+ZHzfo1MKKktgPgJiZSqFaKfCgKhGzzX1UcQ05DhgHup9Xx/EKpQlt0n4X5UMW7uysvk0sYDZoGirN5lZ90pIwKBgQDIaGpvzunerQDdQExdAJAB3ffwFKuKab2APnSqOZm3YNJJ3gJJ5WoZW4jU1huB2hnOtYB2YsF1pLS6iTZM3dtJB4IrykNym/RPqoiylJaqy29QXFN1Dpl2OBjBzSqBF/U6OUp+i5MirmXDJbZ6Wy8MYk07Lt0h3ZeZ2jDDyXLaHwKBgFnKHLNjtrurMQWU7a1IVEhkBAhJlcOHbQy8eO7pxvjXtuwgytgPgfSmyiyxJlBr/fdN02VlytGvOxSfQ/3AZ2sWYlboV5QYJfU0GdQ7KiSm9GUDIdLm3v827iV6WLKngzjDK3dU8Nt1JOdUOgewmfWK9Vb07wn9A5N121gc2s4dAoGAOcq6nuGJWbiEHkmTe+JUpOUwwaAEU9boWdoo5InVxSb7nWeTO2IX9ZYK4G2Z4xlVBeIbWIhkB5vmrkAxU3tK6EVtCVm7l4pXqr2fy/fDdx9RS1hEjOYX5RqKMSEMF8wj4JVy4Qk49fBa0irG84PmDmkuolmCVWjEdg6Qr3UhVLsCgYEAoNWG3HXWH8AkMkW1qHjlYTrJdZrLccnrVdYJqcb5LcJm+fTDUknR1HLcQIcUepy+p+tVBfHFobW5vD6Z83nFW/BEv39bVPLATogYZA4L8+ZJTxy9Cdo+x5+5V4FfITu1RP2axaDUb7coGlQGbKrr7SzKfq5ziYmIVin0FjpYdYU=',
        //支付宝公钥，由支付宝生成: 请填写支付宝公钥，一行字符串
        'alipayrsaPublicKey'    =>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2vWSnud2ZwFC//dlSA7MXhDuOSzMGy/JJlR+ib2So9daoe9uUQzET2/I8BtvA2UdK6sxGYOjj/MPy8TfnNPPl8+jCdwoyhSY6+fjRbJfksM96rae9/qcLFUAUfiE0qyZjOTvS2z+UuOF1QqUu6YJSaPrWYO2+UUjBfcFwW6Nd4njhNaLBokBnv+kLt0yfPhrkRTcCShuG+vfnVWQmcl14lWPanLhLe+CZLZ/J2NFLAnIqIz7SYtF85D4YtWCDpUezvrbLmrSKVeCoxuU8y3GosGPY6P/WoR7fd8Pilgw6FU+oxFlVLmd61ePPM2EbAT3/bzMwdYATPoECVTHkK+ErwIDAQAB',

    ],
    
    'alipay_xcx_config' => [
        //支付宝 支付配置
        'gatewayUrl'            => 'https://openapi.alipay.com/gateway.do',//支付宝网关（固定)'
        'appId'                 => '2021004109696102',//APPID即创建应用后生成
        //由开发者自己生成: 请填写开发者私钥去头去尾去回车，一行字符串
        'rsaPrivateKey'         => 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCqrwTv1HkvjmQk5XvfvA9yFbb7Vj2nsJX3kQzzo/5xiT2MKArHncaBFqVW7F2k+VfH0Iv+11jtTSbxaKVBPNydWG3fLVppAzOb5SDPHoJQJnY6txAnk67zBd9z3GbOxrxxJ9SiX70g5vot8Qa/Js3HEHagxGwMhO8FMtrGDV+ccZ6JvhTGWJif9DkJHWW/WcUh2ZL9D8DfkfZYyNfr8uW2dCTedwGRZ4OmKhSzvibvHg7C5nFQmRn57B/kS5XjrdGk7tjtOgeMaQ17IyP8o4Q9TUDqDpe1Fyj3mro/Tgs1mH6PrDr/89F6lWPRqHCNZE1kQi2GXDjbBomPje7Qx/vDAgMBAAECggEANv6FXLDdCxGZ+rlmHER/xYZxmrHC09D1wPqfbbEdPn+1sP8F2iNf3h/pzgQCeDFOszbipI3GPU9qiMXq2QY/HwNrA0mdo9BARK6iz5lI64I4/doV/mp7KBpwUOhmx6EI/nyS6m5mhb9mRH8waU6bZtRLJKmlFrOOP+KO0tlkQQdi5xXYqLHJGIDWED58lfo2uFs6qoJcvr1mSt73QPw1akiWkmZkWZqfzf65rPwCSzCWYAt9hdlhkCZ5CI6qlqKW3ig93HegS6VAj9kbPLWe1Jl/YU1oGDLmi1vAV6MX2BLhjXktT6U4cKBADT/+njFtqplc/E7ppZMHYofyTpXTIQKBgQDunL9NyxqE85XxHZGnwKZOpYl9lD6efsTWAoCcpjNHfEtJHGgz0OkyiN6IYcFch+EkbgUPu7qdsEMWeM1lhp+Rd8keN1F5cHZPINUTwUdAgE3kqZ3RgbJfx4JE6KFcdbtHz0Uy4inw6fsBiV2K3bgUo9YXvz1EvhXV3oFcUf7IlQKBgQC3HxSaSaCxJ3ihbU8pvjfwqM6E+R9ppqe5kyznVHLfFP0nqdj9kSz5rBRRQg+ddYI+K4p1+zRsjp5OJc+GFc28Q3cmjX6u8s53rwD4D1FDFLgfdGqlpEzL7LWhkpJO4wmpxm5eeWlff/YT8kN6ro6Lh9doS12JIRo/6tyyz2Gk9wKBgQCl6FcbwBywVK3s+KJOAaWhCXiP2IOxsHMsWpESWn1NNx8htp69aIS8nm4cZdwMem4Q5m6egek/u07qURR/gxtwCdnNKKl9xrR8UFfXZIwmTQ/b7hPNmGBuOEpbn2SS5UlSpMt4lciTuhzM9LYV2BQmRcSWvmHbak/EZPGNP3XoAQKBgD45LGoEzLqFnALWPskDXsTCx3H9qMPgoit3rBFq66GL4z2gBCCdPPgVlc3Ksb2iWUBA0UqnsjeU+ou5Y1u/euoWzpzmBX7y+F9Isv6XTdiKaMofZ8GjI4lDhLBDOr3dfcIXsBcgEEMoGvjKIE3GlJ8q6HIC8eSPv/iqGJYVy6sfAoGAYELUoLOvFZKgOlY3kfA1KVHVH7GEfx9ePf6gvGknYjCfM20PLSp/wfnND5qFxpRaqMDkmfsX6ENaughwJamuFeTkYJnyJvo82RJJraAEFlaDJdQAUFfpTbvOkcuAMs6c8xAexDviV9prIoGvIUeZuMTB+Pd3j/5YaCfVLtK4B4s=',
        //支付宝公钥，由支付宝生成: 请填写支付宝公钥，一行字符串
        'alipayrsaPublicKey'    =>  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmEVxu16ryFfF7bZMYOkAA6l0/Mwl3CL3wUBuyMVjuX3xNGSjpRHRHc7SkqD4EVDm0D5xhYV/9kk00USobjoJrRSMvt7WAEDKGl3sb6JZuy116eON0kydEQYzLb3i+SD+EMvxdT7ZpMuDcu42KNqVE3mMKsMPgyubIzRD7BFqK2eTcv6fkSF3n+wmz2lLXKdcjJRuc90QcT7B8Pedc9/OrcHLfAQ1YrflZ9mW/tbs9K7eEwm+OAe1ItWVYkj/hmcVpz8eGyxIjPU+JnIx3JrqwFsuzJKYSxjuhqorl3bkKmfghyQmR/wudK5qJrkjNnyFozjTvaRx+NL0lC1xkVu27wIDAQAB',

    ],
];