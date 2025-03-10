(function ($) {
  let logs = [];

  if (typeof ajax_logger_data !== 'undefined' && ajax_logger_data.page_id) {
      $(document).ajaxComplete(function (event, xhr, settings) {
          const timestamp = new Date().toISOString();
          const requestData = {
              timestamp: timestamp,
              url: settings.url,
              method: settings.type,
              payload: settings.data,
              status: xhr.status,
              response: xhr.responseText,
              elapsedTime: new Date() - new Date(timestamp),
          };
          logs.push({ type: 'ajax', data: requestData });
      });

      window.onerror = function (message, source, lineno, colno, error) {
          const timestamp = new Date().toISOString();
          logs.push({
              type: 'error',
              data: {
                  timestamp: timestamp,
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
          const timestamp = new Date().toISOString();
          logs.push({
              type: 'alert',
              data: {
                  timestamp: timestamp,
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
                      logs = []; 
                  }
              });
          }
      }, 5000);
  }
})(jQuery);