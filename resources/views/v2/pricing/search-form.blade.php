<section>
  <form id="search-form" action="{{ url('v2/pricing/index') }}" method="GET" class="form-inline" role="form">
    <div class="form-group col-sm-1 col-sm-offset-3">
      <select name="marketplace" id="inputMarketplace" class="form-control col-sm-3" required="">
        <option value="">-- select -- </option>
        @foreach($marketplaces as $marketplace)
        <option value="{{ $marketplace->id }}" {{ ($marketplace->id == old('marketplace')) ? 'selected' : '' }}>{{ $marketplace->id }}</option>
        @endforeach
      </select>
    </div>

    <div class="input-group col-sm-6 col-sm-offset-1">
      <div class="input-group-addon"><span class="glyphicon glyphicon-search" aria-hidden="true"></span></div>
      <input type="text" name="search" class="form-control" id="search" value="{{ old('search') }}" placeholder="Search Master SKU, Product Name, Marketplace SKU, Brand" value="">
      <span class="input-group-btn">
        <button type="submit" class="btn btn-default">Search</button>
      </span>
    </div>
  </form>
</section>
