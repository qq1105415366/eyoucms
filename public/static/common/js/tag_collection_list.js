
var tag_collection_list = true;  //用于在外面判断是否已经引用过本文件
var ey_jquery_1624608277 = false;
if (!window.jQuery) {
    ey_jquery_1624608277 = true;
} else {
    var ey_jq_ver_1624608277 = jQuery.fn.jquery;
    if (versionStringCompare(ey_jq_ver_1624608277,'1.8.0') === -1) {
        ey_jquery_1624608277 = true;
    }
}
if (ey_jquery_1624608277) {
    document.write(unescape("%3Cscript src='"+root_dir_v379494+"/public/static/common/js/jquery.min.js?v=v1.6.4' type='text/javascript'%3E%3C/script%3E"));
    document.write(unescape("%3Cscript type='text/javascript'%3E try{jQuery.noConflict();}catch(e){} %3C/script%3E"));
}

if (!window.layer || !layer.v) {
    document.write(unescape("%3Cscript src='"+root_dir_v379494+"/public/plugins/layer-v3.1.0/layer.js' type='text/javascript'%3E%3C/script%3E"));
}

//比较版本号大小，返回值（1：前大于后，0：相等，-1：前小于后）
function versionStringCompare(preVersion, lastVersion){
    var sources = preVersion.split('.');
    var dests = lastVersion.split('.');
    var maxL = Math.max(sources.length, dests.length);
    var result = 0;
    for (var i = 0; i < maxL; i++) {
        var preValue = sources.length>i ? sources[i]:0;
        var preNum = isNaN(Number(preValue)) ? preValue.charCodeAt() : Number(preValue);
        var lastValue = dests.length>i ? dests[i]:0;
        var lastNum =  isNaN(Number(lastValue)) ? lastValue.charCodeAt() : Number(lastValue);
        if (preNum < lastNum) {
            result = -1;
            break;
        } else if (preNum > lastNum) {
            result = 1;
            break;
        }
    }
    return result;
}

/**
 * 收藏、取消
 * @return {[type]} [description]
 */
function ey_v378141(aid,cla,obj)
{
    var cancel_v379494 = window['cancel_v379494_'+aid];
    var collected_v379494 = window['collected_v379494_'+aid];
    var users_id = getCookie_v378141('users_id');
    if (!users_id) {
        if (document.getElementById('ey_login_id_v665117')) {
            $('#ey_login_id_v665117').trigger('click');
        } else {
            window.location.href = loginurl_v379494;
            /*if (!window.layer) {
                alert('请先登录');
            } else {
                var layerindex = layer.alert('请先登录', {id: 'layer_collection_v378141' , icon: 5, title: false}, function(){
                    window.location.href = loginurl_v379494;
                });
                //重新给指定层设定top等
                var top = 150;
                var top2 = document.getElementById("layer_collection_v378141").parentNode.style.top;
                top2 = top2.replace('px', '');
                if (top2 > 150 && top2 < 500) {
                    top = top2;
                }
                layer.style(layerindex, {
                    top: top
                }); 
            }*/
            return false;
        }
    }

    //步骤一:创建异步对象
    var ajax = new XMLHttpRequest();
    //步骤二:设置请求的url参数,参数一是请求的类型,参数二是请求的url,可以带参数,动态的传递参数starName到服务端
    ajax.open("post", root_dir_v379494+"/index.php?m=api&c=Ajax&a=collect_save", true);
    // 给头部添加ajax信息
    ajax.setRequestHeader("X-Requested-With","XMLHttpRequest");
    // 如果需要像 HTML 表单那样 POST 数据，请使用 setRequestHeader() 来添加 HTTP 头。然后在 send() 方法中规定您希望发送的数据：
    ajax.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    //步骤三:发送请求+数据
    ajax.send('aid='+aid+'&_ajax=1');
    //步骤四:注册事件 onreadystatechange 状态改变就会调用
    ajax.onreadystatechange = function () {
        //步骤五 如果能够进到这个判断 说明 数据 完美的回来了,并且请求的页面是存在的
        if (ajax.readyState==4 && ajax.status==200) {
            var json = ajax.responseText;
            var res = JSON.parse(json);
            if (1 == res.code) {
                if ('on' == cla){
                    if (res.data.opt == 'add') {
                        if (cancel_v379494) {
                            obj.classList.remove(cancel_v379494);
                        }
                        if (collected_v379494) {
                            obj.classList.add(collected_v379494);
                        }
                        if (document.getElementById("ey_cnum_v379494_"+aid)) {
                            var collection_num = document.getElementById("ey_cnum_v379494_"+aid).innerHTML;
                            collection_num = parseInt(collection_num) + 1;
                            document.getElementById("ey_cnum_v379494_"+aid).innerHTML = collection_num;
                        }
                    } else {
                        if (collected_v379494) {
                            obj.classList.remove(collected_v379494);
                        }
                        if (cancel_v379494) {
                            obj.classList.add(cancel_v379494);
                        }
                        if (document.getElementById("ey_cnum_v379494_"+aid)) {
                            var collection_num = document.getElementById("ey_cnum_v379494_"+aid).innerHTML;
                            collection_num = parseInt(collection_num) - 1;
                            if (collection_num < 0) {
                                collection_num = 0;
                            }
                            document.getElementById("ey_cnum_v379494_"+aid).innerHTML = collection_num;
                        }
                    }
                }else{
                    var afterHtml = '';
                    if (res.data.opt == 'add') {
                        afterHtml = collected_v379494;
                        if (document.getElementById("ey_cnum_v379494_"+aid)) {
                            var collection_num = document.getElementById("ey_cnum_v379494_"+aid).innerHTML;
                            collection_num = parseInt(collection_num) + 1;
                            document.getElementById("ey_cnum_v379494_"+aid).innerHTML = collection_num;
                        }
                    } else {
                        afterHtml = cancel_v379494;//加入收藏
                        if (document.getElementById("ey_cnum_v379494_"+aid)) {
                            var collection_num = document.getElementById("ey_cnum_v379494_"+aid).innerHTML;
                            collection_num = parseInt(collection_num) - 1;
                            if (collection_num < 0) {
                                collection_num = 0;
                            }
                            document.getElementById("ey_cnum_v379494_"+aid).innerHTML = collection_num;
                        }
                    }
                    obj.innerHTML = afterHtml;
                }
                if (!window.layer) {
                    alert(res.msg);
                } else {
                    layer.msg(res.msg, {time: 1000});
                }
            }
        }
    }
}


/**
 * 异步判断是否收藏
 * @return {[type]} [description]
 */
function ey_v377550(aid,cla)
{
    var cancel_v379494 = window['cancel_v379494_'+aid];
    var collected_v379494 = window['collected_v379494_'+aid];
    var users_id = getCookie_v378141('users_id');

    if ((document.getElementById("ey_v378141_"+aid) || document.getElementById("ey_cnum_v379494_"+aid)) && 0 < aid) {
        var obj = '';
        if (document.getElementById("ey_v378141_"+aid)) {
           obj = document.getElementById("ey_v378141_"+aid);
           // 收藏之前的html文案
           beforeHtml1595661966 = obj.innerHTML;
           // console.log(beforeHtml1595661966);return false; 
        }

        if (0 < users_id) {
            // 正在加载
            var loading = '<img src="data:image/gif;base64,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" />';
            if (!obj) {
                obj.innerHTML = loading;
            }
        }
        //步骤一:创建异步对象
        var ajax = new XMLHttpRequest();
        //步骤二:设置请求的url参数,参数一是请求的类型,参数二是请求的url,可以带参数,动态的传递参数starName到服务端
        ajax.open("get", root_dir_v379494+"/index.php?m=api&c=Ajax&a=get_collection&aid="+aid, true);
        // 给头部添加ajax信息
        ajax.setRequestHeader("X-Requested-With","XMLHttpRequest");
        // 如果需要像 HTML 表单那样 POST 数据，请使用 setRequestHeader() 来添加 HTTP 头。然后在 send() 方法中规定您希望发送的数据：
        ajax.setRequestHeader("Content-type","application/x-www-form-urlencoded");
        //步骤三:发送请求+数据
        ajax.send();
        //步骤四:注册事件 onreadystatechange 状态改变就会调用
        ajax.onreadystatechange = function () {
            //步骤五 如果能够进到这个判断 说明 数据 完美的回来了,并且请求的页面是存在的
            if (ajax.readyState==4 && ajax.status==200) {
                var json = ajax.responseText;
                var res = JSON.parse(json);
                if (1 == res.code) {
                    if (0 < users_id) {
                        if ('on' == cla){
                            if (cancel_v379494) {
                                obj.classList.remove(cancel_v379494);
                            }
                            if (collected_v379494) {
                                obj.classList.add(collected_v379494);
                            }
                        } else{
                            // 收藏之后的html文案
                            if (obj) obj.innerHTML = collected_v379494;
                        }
                    }
                    if (document.getElementById("ey_cnum_v379494_"+aid)) {
                        document.getElementById("ey_cnum_v379494_"+aid).innerHTML = res.data.total;
                    }
                } else {
                    if (0 < users_id) {
                        if ('on' == cla){
                            if (collected_v379494) {
                                obj.classList.remove(collected_v379494);
                            }
                            if (cancel_v379494) {
                                obj.classList.add(cancel_v379494);
                            }
                        } else{
                            // 收藏之后的html文案
                            if (obj) obj.innerHTML = cancel_v379494;
                        }
                    }
                    if (document.getElementById("ey_cnum_v379494_"+aid)) {
                        document.getElementById("ey_cnum_v379494_"+aid).innerHTML = res.data.total;
                    }
                }
            }
        }
    }
}

// 读取 cookie
function getCookie_v378141(c_name)
{
    if (document.cookie.length>0)
    {
        c_start = document.cookie.indexOf(c_name + "=")
        if (c_start!=-1)
        {
            c_start=c_start + c_name.length+1
            c_end=document.cookie.indexOf(";",c_start)
            if (c_end==-1) c_end=document.cookie.length
            return unescape(document.cookie.substring(c_start,c_end))
        }
    }
    return "";
}