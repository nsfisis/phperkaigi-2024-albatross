document.addEventListener('DOMContentLoaded', () => {
  const aggregatedStatusElem = document.getElementsByClassName('js-aggregated-execution-status')[0];
  const aggregatedStatusLoadingIndicatorElem = document.getElementsByClassName('js-aggregated-execution-status-loading-indicator')[0];
  const answerId = aggregatedStatusElem.dataset.answerId;

  const getElemsMap = cls => new Map(
    Array.from(document.getElementsByClassName(cls) ?? [])
      .map(e => [parseInt(e.dataset.testcaseExecutionId), e])
  );
  const statusElemsMap = getElemsMap('js-testcase-execution-status');
  const statusLoadingIndicatorElemsMap = getElemsMap('js-testcase-execution-status-loading-indicator');
  const stdoutElemsMap = getElemsMap('js-testcase-execution-stdout');
  const stderrElemsMap = getElemsMap('js-testcase-execution-stderr');

  if (!aggregatedStatusLoadingIndicatorElem) {
    return;
  }

  const apiUrl = `${process.env.ALBATROSS_BASE_PATH}/api/answers/${answerId}/statuses`;

  let timerId;
  timerId = setInterval(() => {
    fetch(apiUrl)
      .then(response => response.json())
      .then(({ aggregated_status, testcase_executions }) => {
        for (const ex of testcase_executions) {
          const statusElem = statusElemsMap.get(ex.id);
          const loadingIndicatorElem = statusLoadingIndicatorElemsMap.get(ex.id);
          const stdoutElem = stdoutElemsMap.get(ex.id);
          const stderrElem = stderrElemsMap.get(ex.id);

          const { status, stdout, stderr } = ex;
          if (status.label === statusElem.textContent) {
            continue;
          }
          statusElem.textContent = status.label;
          stdoutElem.textContent = stdout;
          stderrElem.textContent = stderr;
          if (loadingIndicatorElem && !status.show_loading_indicator) {
            loadingIndicatorElem.remove();
          }
        }

        if (aggregated_status.label === aggregatedStatusElem.textContent) {
          return;
        }
        aggregatedStatusElem.textContent = aggregated_status.label;
        if (!aggregated_status.show_loading_indicator) {
          aggregatedStatusLoadingIndicatorElem.remove();
          clearInterval(timerId);
        }
      });
  }, 5 * 1000);
});
