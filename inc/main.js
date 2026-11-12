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
			s.innerHTML = '<img src=img/' + img + '.gif /> ' + text + '';
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
		listenTimeout = setTimeout(fun, T);
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
                window.setTimeout(function(){ window.location.assign(result.url); }, 500);
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

















