document.addEventListener('DOMContentLoaded', () => {
  const phperTokenElem = document.getElementsByClassName('js-phper-token')[0];

  const aggregatedStatusElem = document.getElementsByClassName('js-aggregated-execution-status')[0];
  const aggregatedStatusLoadingIndicatorElem = document.getElementsByClassName('js-aggregated-execution-status-loading-indicator')[0];
  const answerId = aggregatedStatusElem.dataset.answerId;

  const getElemsMap = cls => new Map(
    Array.from(document.getElementsByClassName(cls) ?? [])
      .map(e => [parseInt(e.dataset.testcaseExecutionId), e])
  );
  const statusElemsMap = getElemsMap('js-testcase-execution-status');
  const statusLoadingIndicatorElemsMap = getElemsMap('js-testcase-execution-status-loading-indicator');

  if (!aggregatedStatusLoadingIndicatorElem) {
    return;
  }

  const apiUrl = `${process.env.ALBATROSS_BASE_PATH}/api/answers/${answerId}/statuses`;

  let timerId;
  timerId = setInterval(() => {
    fetch(apiUrl)
      .then(response => response.json())
      .then(({ aggregated_status, testcase_executions, phper_token }) => {
        if (phper_token) {
          phperTokenElem.innerHTML = `<div class="alert alert-success">バーディー！ ${phper_token}</div>`;
        }

        for (const ex of testcase_executions) {
          const statusElem = statusElemsMap.get(ex.id);
          const loadingIndicatorElem = statusLoadingIndicatorElemsMap.get(ex.id);

          const { status } = ex;
          if (status.label === statusElem.textContent) {
            continue;
          }
          statusElem.textContent = status.label;
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
