/* TxtHarbor-build:1.5.7 */
/*
* 程序名称：162100留言本（吧）
* 程序编码：UTF-8
* 作者：162100.com
* 网址：http://bbs.162100.com
* 声明：仅供代码爱好者学习交流，禁用于商业目的，请保留此版权信息
*/


var main = null;
var htmlbody = parent != null && parent != self ? window.parent: window.self;
var listenTimeout = null;

main = {

	getCookie: function(name) {
		var arr = document.cookie.match(new RegExp("(^| )" + name + "=([^;]*)(;|$)"));
		if (arr != null && arr != false) {
			return decodeURIComponent(arr[2]);
		} else {
			//return undefined;
			return null;
		}
	},

	addSubmitSafe_: function(img, text, fun, T) {
		var s = htmlbody.document.getElementById('submit_safe_in22');
		if (s != null) {
	        // Legacy server messages can contain user-controlled text. Never parse it as HTML.
        s.textContent = '';
        var icon = htmlbody.document.createElement('img');
        icon.src = 'img/' + (img === 'ok' ? 'ok' : 'i') + '.gif';
        icon.alt = '';
        s.appendChild(icon);
        s.appendChild(htmlbody.document.createTextNode(' ' + String(text)));
		} else {
			htmlbody.main.addSubmitSafe();
			this.addSubmitSafe_(img, text);
		}
		if (!arguments[2] || arguments[2] == 'undefined') {
			fun = "main.delSubmitSafe()";
		}
		if (!arguments[3] || arguments[3] == 'undefined') {
			T = 3000;
		}
		if (listenTimeout != null) {
			clearTimeout(listenTimeout);
			listenTimeout = null;
		}
        var callback = typeof fun === 'function' ? fun : function() {
            // Preserve legacy known navigation/close actions, but never eval strings.
            if (fun === 'htmlbody.location.reload();') htmlbody.location.reload();
            else if (fun === 'main.delSubmitSafe()' || fun === undefined || fun === null) main.delSubmitSafe();
            else if (fun === 'htmlbody.location.reload()') htmlbody.location.reload();
            else main.delSubmitSafe();
        };
        listenTimeout = setTimeout(callback, T);
	},

	addSubmitSafe: function() {
		var parShowH = htmlbody.document.documentElement.clientHeight || htmlbody.window.innerHeight;
		parShowH = !isNaN(parShowH) && parShowH > 0 ? parShowH + 'px' : '100%';
		var s = htmlbody.document.getElementById('submit_safe');
		if (s == null) {
			var s = document.createElement('DIV');
			s.id = 'submit_safe';
			s.style.height = parShowH;
			htmlbody.document.body.appendChild(s);
		} else {
			s.style.height = parShowH;
		}

		s.innerHTML = '<div id="submit_safe_in"></div><table width="100%" height="100%" cellpadding="0" cellspacing="0" id="submit_safe_in2"><tr><td><div id="submit_safe_in22">&#x8BF7;&#x7A0D;&#x5019; &#x2026;</div></td></tr></table>';

		htmlbody.document.getElementById('submit_safe_in2').onmousedown = function() {
			htmlbody.document.getElementById('submit_safe_in22').innerHTML = '&#x6B63;&#x5728;&#x5173;&#x95ED; &#x2026;';
			listenTimeout = setTimeout(this.delSubmitSafe, 1500);
		};
	},

	delSubmitSafe: function() {
		if (listenTimeout != null) {
			clearTimeout(listenTimeout);
			listenTimeout = null;
		}
		var o = document.getElementById('submit_safe_in2');
		if (o != null && o.onmousedown != null) {
			o.onmousedown = null;
		}
		o = null;
		while (htmlbody.document.getElementById('submit_safe') != null) {
			htmlbody.document.getElementById('submit_safe').style.display = 'none';
			htmlbody.document.body.removeChild(htmlbody.document.getElementById('submit_safe'));
		}
	},
	
	strlen: function (str) {
    	return Array.from ? Array.from(str).length : str.length
	},

	/*
	strlen: function(text) {
		var len = 0;
		for (var i = 0; i < text.length; ++i) {
			if (text.charCodeAt(i) > 127 || text.charCodeAt(i) == 94) {
				len += 2;
			} else {
				len++;
			}
		}
		return len;
	},
	*/
	
	postChk: function() {
		var s = document.getElementById('subject');
		var min_word_count = this.minWordCount != 'undefined' && !isNaN(this.minWordCount) && this.minWordCount > 0 ? this.minWordCount : 7;
		if (s != null) {
			var str1 = s.value;
			if (str1 != '') {
				var len1 = this.strlen(str1);
				if (len1 < min_word_count) {
					this.addSubmitSafe_('i', '留言题目请' + min_word_count + '个字符起');
					//s.focus();
					s.style.backgroundColor = '#FFFD45';
					return false;
				}
				if (len1 > this.maxSubjectWordCount) {
					this.addSubmitSafe_('i', '请缩短你的留言题目至100字符以下。现在为' + len1 + '字符，大约得减' + (len1 - this.maxSubjectWordCount) + '字符');
					//s.focus();
					s.style.backgroundColor = '#FFFD45';
					return false;
				}
			}
		}
		var c = document.getElementById('content');
		if (c != null) {
			var str2 = c.value;
			var len2 = this.strlen(str2);
			if (str2.trim() === '' || len2 < min_word_count) {
				this.addSubmitSafe_('i', '留言内容请' + min_word_count + '个字符起');
				//c.focus();
				c.style.backgroundColor = '#FFFD45';
				return false;
			}

			if (len2 > this.maxContentWordCount) {
				this.addSubmitSafe_('i', '请缩短你的留言内容至' + this.maxContentWordCount + '字符以下。现在为' + len2 + '字符，大约得减' + (len2 - this.maxContentWordCount) + '字符');
				//c.focus();
				c.style.backgroundColor = '#FFFD45';
				return false;
			}
		}
		var i = document.getElementById('imcode');
		if (i != null) {
			var str3 = i.value.replace(/[\u200b-\u200f\uFEFF\u202a-\u202e]/ig, '').replace(/^\s+|\s+$/g, '');
			if (str3 == '' || (str3 != '' && str3 != this.getCookie('regimcode_162100bbs'))) {
				this.addSubmitSafe_('i', (str3 == '' ? '验证码不能空！' : '验证码不符！'));
				//i.focus();
				i.style.backgroundColor = '#FFFD45';
				return false;
			} else {
				i.value = str3;
			}
		}
		return true;
	},

    showSubmitFeedback: function(message, error) {
        var el = document.getElementById('submit-feedback');
        if (!el) { this.notify(message, error); return; }
        el.textContent = message;
        el.className = error ? 'submit-feedback submit-error' : 'submit-feedback submit-ok';
        el.hidden = false;
    },

    submitMessage: function(event, form) {
        if (this._writeBusy) { if (event) event.preventDefault(); return false; }
        if (!this.postChk()) { if (event) event.preventDefault(); return false; }
        // Keep the legacy hidden-iframe path as a fallback for old browsers.
        if (!window.fetch || !window.FormData || !window.Promise) {
            this.addSubmitSafe();
            return true;
        }
        if (event) event.preventDefault();
        var that = this;
        var submitButton = form.querySelector('button[type="submit"]');
        var oldLabel = submitButton ? submitButton.innerHTML : '';
        var feedback = document.getElementById('submit-feedback');
        if (feedback) feedback.hidden = true;
        this._writeBusy = true;
        if (submitButton) { submitButton.disabled = true; submitButton.textContent = '正在提交…'; }
        var data = new FormData(form);
        data.set('ajax', '1');
        fetch(form.action, {
            method: 'POST', body: data, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(response) {
            return response.text().then(function(body) {
                var result;
                try { result = JSON.parse(body); } catch (e) {
                    throw new Error('服务器未返回有效结果（HTTP ' + response.status + '），可能是 PHP 报错或文件权限问题，请检查服务器错误日志。提交状态无法确认，请先查看留言列表，避免重复发布。');
                }
                if (!response.ok || !result || result.ok !== true) {
                    throw new Error(result && result.message ? result.message : '发布失败（HTTP ' + response.status + '）');
                }
                if (!result.url || !/^\.\/\?focus=\d+-\d+$/.test(result.url)) {
                    throw new Error('服务端已确认保存，但未返回有效跳转位置。请到留言列表查看，避免重复发布。');
                }
                that.showSubmitFeedback('发布成功，正在定位刚提交的留言…', false);
                var passwordField = form.querySelector('input[name="access_password"]');
                var password = passwordField ? passwordField.value : '';
                if (password && result.share_url && /^entry\.php\?id=[a-f0-9]{24}$/.test(result.share_url)) {
                    var link = new URL(result.share_url, window.location.href);
                    link.hash = 'key=' + encodeURIComponent(password);
                    // Copy the password-bearing link only after the server confirms the save.
                    return that.copyShareText(link.href).then(function() {
                        window.location.replace(result.url);
                    });
                }
                window.location.replace(result.url);
            });
        }).catch(function(err) {
            that.showSubmitFeedback(err && err.message ? err.message : '网络异常，无法确认是否已提交，请先查看留言列表，避免重复发布。', true);
            that._writeBusy = false;
            if (submitButton) { submitButton.disabled = false; submitButton.innerHTML = oldLabel; }
        });
        return false;
    },

    runGetWordCount: function(id, limit) {
        var c = document.getElementById(id);
        var counter = document.getElementById('max_words_' + id);
        if (!c || !counter) return;
        var len = Array.from ? Array.from(c.value).length : c.value.length;
        counter.textContent = len + ' / ' + limit + ' 字符';
        counter.classList.toggle('over-limit', len > limit);
    },

    clearInput: function(id) {
        var c = document.getElementById(id || 'content');
        if (!c) return;
        c.value = '';
        this.runGetWordCount(c.id, c.id === 'subject' ? this.maxSubjectWordCount : this.maxContentWordCount);
        c.focus();
    },

    pasteInput: async function(id) {
        var c = document.getElementById(id || 'content');
        if (!c) return;
        try {
            if (!navigator.clipboard || !navigator.clipboard.readText) throw new Error('unavailable');
            var text = await navigator.clipboard.readText();
            c.setRangeText(text, c.selectionStart, c.selectionEnd, 'end');
            c.dispatchEvent(new Event('input', {bubbles:true}));
            c.focus();
        } catch(e) {
            // Browser permissions require a secure context (HTTPS or localhost).
            c.focus();
            this.notify('无法自动读取剪贴板，请按 Ctrl+V / ⌘V 粘贴', true);
        }
    },

    copyShareText: function(value) {
        var that = this;
        var manual = function () {
            var t = document.createElement('textarea');
            t.value = value;
            t.setAttribute('readonly', 'readonly');
            t.style.cssText = 'position:fixed;left:-200vw;top:0;opacity:0';
            document.body.appendChild(t); t.select();
            var success = false;
            try { success = document.execCommand('copy'); } catch (_) { }
            t.remove();
            if (!success) window.prompt('请手动复制分享链接（含访问密码）', value);
            else that.notify('分享链接已复制');
            return success;
        };
        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
            return navigator.clipboard.writeText(value).then(function () {
                that.notify('分享链接已复制');return true;
            }, function () { return manual(); });
        }
        return Promise.resolve(manual());
    },

    shareEntry: function(button) {
        var url=button.getAttribute('data-share-url');
        if (!url) return;
        var absolute=new URL(url, window.location.href);
        var that=this;
        if (button.getAttribute('data-share-protected')!=='1') { this.copyShareText(absolute.href); return; }
        var ready=button.getAttribute('data-share-ready');
        if (ready) {this.copyShareText(new URL(ready,window.location.href).href);return;}
        if (!window.fetch || !window.FormData) {
            this.notify('当前浏览器不支持快速分享，请使用现代浏览器',true); return;
        }
        var csrf=button.getAttribute('data-share-csrf') || '';
        button.disabled=true;
        var recover=new FormData();recover.set('action','share_recover');recover.set('csrf',csrf);
        fetch(absolute.href,{method:'POST',credentials:'same-origin',body:recover,headers:{'Accept':'application/json'}})
        .then(function(response){return response.json().then(function(result){
            if (response.ok && result && result.ok && result.url) return that.copyShareText(new URL(result.url,window.location.href).href);
            if (result && result.code==='needs_password') return that.shareEntryFallback(absolute,csrf);
            throw new Error(result && result.message ? result.message : '无法读取当前浏览器的分享信息');
        });})
        .catch(function(error){that.notify(error.message || '复制分享链接失败',true);})
        .finally(function(){button.disabled=false;});
    },

    shareEntryFallback: function(absolute,csrf) {
        var password=window.prompt('此浏览器尚未保存分享密码，请输入一次访问密码：');
        if (password===null || password==='') return Promise.resolve(false);
        var that=this,form=new FormData();
        form.set('action','share_verify');form.set('csrf',csrf);form.set('password',password);
        return fetch(absolute.href,{method:'POST',credentials:'same-origin',body:form,headers:{'Accept':'application/json'}})
        .then(function(response){return response.json().then(function(result){
            if(!response.ok || !result || !result.ok) throw new Error(result && result.message ? result.message : '密码验证失败');
            absolute.hash='key='+encodeURIComponent(password);
            return that.copyShareText(absolute.href);
        });});
    },

    generateAccessPassword: function(id, button) {
        var input=document.getElementById(id);
        if (!input) return;
        if (!window.crypto || !window.crypto.getRandomValues) {
            this.notify('浏览器不支持安全随机数，请手动设置密码', true); return;
        }
        var chars='abcdefghijklmnopqrstuvwxyz0123456789';
        var password='';
        // Four characters containing both a lowercase letter and a digit.
        for (var i=0;i<50;i++) {
            var bits=new Uint32Array(4);
            window.crypto.getRandomValues(bits);
            password=Array.from(bits, function(v){return chars[v%chars.length];}).join('');
            if (/[a-z]/.test(password) && /[0-9]/.test(password)) break;
        }
        input.value=password;
        input.type='text';
        input.dispatchEvent(new Event('input',{bubbles:true}));
        var selector=document.querySelector('select[name="password_mode"]');
        if (selector && id==='entry-new-password') selector.value='set';
        input.focus();input.select();
        this.notify('已生成 4 位密码，请妥善保存；更长密码更安全');
    },

    /** Progressive enhancement: keep native POST as fallback. Clipboard or URL
     * exceptions after a successful commit must never be shown as save failures. */
    saveEntrySettings: function(event, form) {
        if (!window.fetch || !window.FormData || !window.Promise) return true;
        if (event) event.preventDefault();
        var data, that=this;
        try { data=new FormData(form); } catch(e) {
            this.notify('表单无法读取，请刷新页面后重试',true);return false;
        }
        data.set('ajax','1');
        var button=form.querySelector('button[type="submit"]');
        if (button) {button.disabled=true;button.textContent='正在保存…';}
        var saved=false;
        fetch(form.getAttribute('action')||'entry.php',{method:'POST',body:data,credentials:'same-origin',headers:{'Accept':'application/json'}})
          .then(function(response){return response.text().then(function(body){
             var result;
             try {result=JSON.parse(body);}catch(e){throw new Error('服务器响应无法解析（HTTP '+response.status+'），修改状态未确认，请先检查留言详情。');}
             if (!response.ok || !result || result.ok!==true) throw new Error(result && result.message ? result.message : '保存失败（HTTP '+response.status+'）');
             if (!/^entry\.php\?id=[a-f0-9]{24}&saved=1$/.test(result.url||'')) throw new Error('服务器已确认保存，但返回的详情地址不正确，请刷新当前留言详情。');
             saved=true;
             var next=result.url;
             var go=function(){window.location.assign(next);};
             var password=data.get('password_mode')==='set'?data.get('new_password'):'';
             if (typeof password==='string' && password && /^entry\.php\?id=[a-f0-9]{24}$/.test(result.share_url||'')) {
                // A validated relative link can be built without URL() for older WebKit.
                var path=window.location.href.split('#')[0].split('?')[0];
                var base=path.slice(0,path.lastIndexOf('/')+1);
                var link=base+result.share_url+'#key='+encodeURIComponent(password);
                try {return Promise.resolve(that.copyShareText(link)).then(go,go);}
                catch(copyError) {go();return;}
             }
             go();
          });})
          .catch(function(e){
             if(saved) {that.notify('修改已保存，请返回详情页查看。',false);return;}
             that.notify(e && e.message ? e.message : '网络异常，保存状态尚未确认，请先检查详情页，避免重复提交。',true);
             if(button){button.disabled=false;button.textContent='保存修改';}
          });
        return false;
    },

    /** Use a native date input plus explicit hour/minute selects. */
    setupExpiryPicker: function(selectId, panelId) {
        var selector=document.getElementById(selectId), panel=document.getElementById(panelId);
        if (!selector||!panel)return;
        var inputs=panel.querySelectorAll('input,select');
        function sync(){
            var shown=selector.value==='custom';
            panel.hidden=!shown;
            for(var i=0;i<inputs.length;i++){
                inputs[i].disabled=!shown;
                if(inputs[i].name==='expiry_date') inputs[i].required=shown;
            }
        }
        selector.addEventListener('change',sync);
        sync();
    },

    copyEntry: async function(id, button) {
        var el = document.getElementById(id);
        if (!el) return;
        var text = el.textContent;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                var helper = document.createElement('textarea');
                helper.value = text;
                helper.style.position = 'fixed'; helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                var worked = document.execCommand('copy');
                document.body.removeChild(helper);
                if (!worked) throw new Error('copy failed');
            }
            if (button) {
                var label = button.textContent;
                button.textContent = '✓ 已复制';
                window.setTimeout(function(){ button.textContent = label; }, 1400);
            }
            this.notify('原文已复制');
        } catch(e) { this.notify('复制失败，请手动选中文本复制', true); }
    },

    notify: function(text, error) {
        var n = document.getElementById('text-toast');
        if (!n) { n = document.createElement('div'); n.id = 'text-toast'; document.body.appendChild(n); }
        n.textContent = text;
        n.className = error ? 'error show' : 'show';
        if (this.toastTimer) clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(function(){ n.className = ''; }, 2500);
    },

    confirmSubarea: function(obj) {
		while (document.getElementById('is') != null) {
			document.getElementById('is').id = '';
		}
		obj.id = 'is';
	},

};




window.beforeunload = window.onunload = function() {
	main = null;
	htmlbody = null;
	listenTimeout = null;
};


















/* v1.5.7: opt-in remote image preview and accessible long-entry folding. */
(function() {
    function setupPreviews() {
        document.querySelectorAll('.th-entry-preview[data-preview-limit]').forEach(function(content) {
            var limit=Number(content.getAttribute('data-preview-limit'));
            if (!Number.isFinite(limit) || limit < 120) return;
            var toggle=content.nextElementSibling;
            if (!toggle || !toggle.classList.contains('th-expand-entry')) return;
            if (content.scrollHeight <= limit+8) return;
            content.style.setProperty('--th-preview-limit',limit+'px');
            content.classList.add('is-collapsed');
            toggle.hidden=false;
            toggle.addEventListener('click',function() {
                var collapsed=content.classList.toggle('is-collapsed');
                toggle.textContent=collapsed?'展开全文':'收起内容';
                toggle.setAttribute('aria-expanded',String(!collapsed));
            });
        });
    }
    if (typeof document.addEventListener !== 'function') return;
    document.addEventListener('click',function(e) {
        var button=e.target.closest && e.target.closest('.md-load-image');
        if (!button) return;
        var wrapper=button.closest('.md-external-image');
        var url=wrapper && wrapper.getAttribute('data-image-url');
        if (!url || !/^https:\/\/[^\s]+$/i.test(url)) return;
        var image=document.createElement('img');
        image.loading='lazy';image.decoding='async';image.referrerPolicy='no-referrer';
        image.alt=(wrapper.querySelector('.md-image-description')||{}).textContent || '外部图片';
        image.addEventListener('error',function(){image.remove();button.hidden=false;});
        button.hidden=true;wrapper.appendChild(image);image.src=url;
    });
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setupPreviews);
    else setupPreviews();
})();
