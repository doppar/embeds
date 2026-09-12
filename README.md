<p align="center">
    <a href="https://doppar.com" target="_blank">
        <img src="https://raw.githubusercontent.com/doppar/doppar/7138fb0e72cd55256769be6947df3ac48c300700/public/logo.png" width="400">
    </a>
</p>

<p align="center">
<a href="https://github.com/doppar/embeds/actions/workflows/tests.yml"><img src="https://github.com/doppar/embeds/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/doppar/embeds"><img src="https://img.shields.io/packagist/dt/doppar/embeds" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/doppar/embeds"><img src="https://img.shields.io/packagist/v/doppar/embeds" alt="Latest Stable Version"></a>
<a href="https://github.com/doppar/embeds/blob/main/LICENSE.md"><img src="https://img.shields.io/github/license/doppar/embeds" alt="License"></a>
</p>

## Doppar Embeds

Doppar Embeds adds semantic search to your models with a single attribute. Mark a column with `#[Embeds]`, and Doppar keeps a numeric representation of its meaning — a vector — in sync automatically,

so `Product::whereSimilarTo('description', 'a durable waterproof backpack')` finds "rugged daypack for hiking in the rain" even though the two share almost no words. Everything runs locally: embeddings are computed on your own server using a small local model, with no external API call, no API key, and no per-request cost.

## Documentation
Read the documentation from doppar official site [Doppar Embeds](https://doppar.com/versions/4.x/doppar-embeds)

---

## Contributing

Thank you for considering contributing to the Doppar framework! The contribution guide can be found in the [Doppar documentation](https://doppar.com/versions/4.x/contributions.html).

## Code of Conduct

In order to ensure that the Doppar community is welcoming to all, please review and abide by the [Code of Conduct](https://doppar.com/versions/4.x/contributions.html#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/doppar/framework/security/policy) on how to report security vulnerabilities.

## License

The Doppar framework is open-sourced software licensed under the [MIT license](LICENSE.md).
