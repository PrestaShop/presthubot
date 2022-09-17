<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>PrestaShop modules release status</title>

    <!-- Bootstrap core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
  </head>

  <body>

    <div class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom box-shadow">
      <h5 class="my-0 mr-md-auto font-weight-normal">PrestaShop modules release status</h5>
    </div>

    <div class="pricing-header px-3 py-3 pt-md-5 pb-md-4 mx-auto text-center">
      <h1 class="display-4">Modules</h1>
      <p class="lead">Static HTML page that displays monitored modules git status. Built on GitHub Actions, GitHub pages on <a href="https://github.com/PrestaShop/ps-monitor-module-releases">PrestaShop/ps-monitor-module-releases</a></p>
      <span class="badge bg-primary">Latest update: {%%latestUpdateDate%%}</span>
    </div>

    <div class="container">
      <table class="table table-striped">
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">Module name</th>
            <th scope="col">Need release?</th>
            <th scope="col">Commits ahead</th>
            <th scope="col">Last release date</th>
            <th scope="col">Pull Request</th>
          </tr>
        </thead>
        <tbody>
          {%%placeholder%%}
        </tbody>
      </table>
    </div>
  </body>
</html>
