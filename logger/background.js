var hostedPath = ""; // path to your history web app

function handleErrors(response) {
    if (!response.ok) {

      fetch(hostedPath + "/errors.php", {
        method: "POST",
        body: JSON.stringify({response}),
        headers: {
          "Content-type": "application/json; charset=UTF-8"
        }
      });

        throw Error(response.statusText);
    }
    return response;
}

function uploadHistory() {
  chrome.identity.getProfileUserInfo(function(userInfo) {

    fetch(hostedPath + "/logger.php?id="+userInfo.id)
    .then((response) => response.text())
    .then((text) => {

      query = {text: ''}
      query.startTime = parseFloat(text);

      history.unlimitedSearch(query).then((historyItems) => {
        if (historyItems.length > 0) {
          historyItems.reverse();
          fetch(hostedPath + "/logger.php", {
            method: "POST",
            body: JSON.stringify({
              id: userInfo.id,
              items: historyItems,
            }),
            headers: {
              "Content-type": "application/json; charset=UTF-8"
            }
          })
          .then(handleErrors)
          .catch(error => console.log(error));
        }
      })
      .then(handleErrors)
      .catch(error => console.log(error));

    })
    .then(handleErrors)
    .catch(error => console.log(error));
  })
}

chrome.alarms.create({
  periodInMinutes: 1
});

chrome.alarms.onAlarm.addListener(() => {
  uploadHistory();
});

const history = {
  getVisits(details) {
    return new Promise(resolve => {
      chrome.history.getVisits(details, function(visitItems) {
        resolve(visitItems)
      })
    })
  },

  search(query) {
    return new Promise(resolve => {
      chrome.history.search(query, function(historyItems) {
        resolve(historyItems)
      })
    })
  },


  unlimitedSearch(query) {
    const now = (new Date).getTime()
    Object.assign(query, {
      endTime: now,
      maxResults: 100,
    })

    const data = {
      visitItemsHash: {},
      historyItems: [],
    }

    function recursiveSearch(query) {
      return history.search(query).then((historyItems) => {
        historyItems = historyItems.filter(historyItem => {
          if (data.visitItemsHash[historyItem.id]) {
            return false
          } else {
            data.visitItemsHash[historyItem.id] = true
            return true
          }
        })

        if (historyItems.length == 0) {
          return data.visitItemsHash
        } else {
          const promises = []
          for (let historyItem of historyItems) {
            const details = {url: historyItem.url}
            const promise = history.getVisits(details)
            promises.push(promise)
          }
          return Promise.all(promises).then((allVisitItems) => {
            let oldestLastVisitTime = now

            for (var i = 0; i < historyItems.length; i++) {
              const historyItem = historyItems[i]
              const visitItems = allVisitItems[i]
              data.visitItemsHash[historyItem.id] = visitItems

              for (visitItem of visitItems) {
                visitItem.title = ''
                Object.assign(visitItem, historyItem)
              }

              if (oldestLastVisitTime > historyItem.lastVisitTime) {
                oldestLastVisitTime = historyItem.lastVisitTime
              }
            }

            query.endTime = oldestLastVisitTime
            return recursiveSearch(query)
          })
        }
      })
    }

    return recursiveSearch(query).then((visitItemsHash) => {
      let allVisitItems = []
      for (visitItems of Object.keys(visitItemsHash)) {
        allVisitItems = allVisitItems.concat(visitItemsHash[visitItems])
      }
      allVisitItems.sort((a, b) => {
        return b.visitTime - a.visitTime
      })
      allVisitItems = allVisitItems.filter(a => {
        return a.visitTime > query.startTime
      })
      return allVisitItems
    })
  }
}
