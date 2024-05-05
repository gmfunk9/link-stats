# Link-Stats

## Overview
Link-Stats is a web application for website maintenance and SEO optimization. It starts as a broken-link scanner and will evolve to provide comprehensive link analytics based on sitemaps. The tool supports concurrent scanning threads for rapid analysis.

## Key Features
- **Concurrent Scanning**: Multiple queue processing for faster link checking.
- **Minimal Setup**: Simple installation with minimal dependencies.

## Installation
1. **Clone the Repository**: Clone the repository to your environment.
2. **Configure Server**: Ensure PHP is installed. Set the server to direct to `index.php`.
3. **Server Compatibility**: Tested on Nginx; tests needed for Apache, IIS, etc.
4. **Run**: Access `index.php` from a browser to start the application.

## Usage
Input a sitemap URL, click 'Check Links' to initiate scanning, and view results in real-time on the interface.

## Suggested Improvements for Contributors
- [ ] **Test on Various Servers**: Test the application on different server environments like Apache, Shared Hosting, Lightspeed, PHP versions, etc.
- [ ] **Test on Various Websites**: Test the application on different websites, possibly compare with competitor apps for false positives.
- [ ] **Enhance Error Handling in XML Parsing**: Improve error handling for XML parsing failures in `get_sitemap.php`.
- [ ] **Refactor JavaScript to Improve Readability**: Refactor JavaScript in `index.php` using modern ES6+ syntax, including async/await where applicable.
- [ ] **Create a Responsive CSS Layout**: Implement a responsive CSS layout for `index.php` to improve user experience on mobile devices.
- [ ] **Optimize CURL Configurations**: Enhance CURL configuration settings in `get_sitemap.php` for more efficient network handling.
- [ ] **Validate User Inputs More Rigorously**: Strengthen user input validation in `index.php` to enhance security and prevent common vulnerabilities.
- [ ] **Implement Detailed Logging for Debugging**: Create a more detailed logging system that can help in debugging and tracking the flow of requests and responses.

## Contributing
Contributions are welcome!

## License
Available under the MIT License.
