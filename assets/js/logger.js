(function ($) {
  let logs = [];

  if (typeof ajax_logger_data !== 'undefined' && ajax_logger_data.page_id) {
      $(document).ajaxComplete(function (event, xhr, settings) {

       
        const payload = new URLSearchParams(settings.data);
            if (payload.get('action') === 'ajax_logger_save_logs') {
              console.log('skipping own ajax request');
                return; 
            }

            const parsedPayload = {};
            for (const [key, value] of payload.entries()) {
                parsedPayload[key] = value;
            }

            let parsedResponse = xhr.responseText;
            try {
                parsedResponse = JSON.parse(xhr.responseText);
            } catch (e) {
            }

            const requestData = {
                url: settings.url,
                method: settings.type,
                payload: parsedPayload, 
                status: xhr.status,
                response: parsedResponse,
            };
            logs.push({ type: 'ajax', data: requestData });
      });

      window.onerror = function (message, source, lineno, colno, error) {
          logs.push({
              type: 'error',
              data: {
                  message: message,
                  source: source,
                  line: lineno,
                  column: colno,
                  stack: error ? error.stack : null,
              },
          });
      };

      const originalAlert = window.alert;
      window.alert = function (message) {
          logs.push({
              type: 'alert',
              data: {
                  message: message,
              },
          });
          originalAlert(message);
      };

      setInterval(function () {
          if (logs.length > 0) {
              $.post(ajax_logger_data.ajax_url, {
                  action: 'ajax_logger_save_logs',
                  nonce: ajax_logger_data.nonce,
                  logs: JSON.stringify(logs),
              }, function (response) {
                  if (response.success) {
                    console.log('Logs array cleared');
                    console.log(logs);
                      logs = []; 
                  }
              });
          }
      }, 3000);
  }
})(jQuery);