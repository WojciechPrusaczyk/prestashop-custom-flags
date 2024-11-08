# Custom flags
Prestashop 1.7 module for adding custom product flags.
Also allows user, to trigger flag below, or above specified item quantity.

![image](https://github.com/user-attachments/assets/63545dfa-bce1-42d9-adb7-18ff2662193c)
## Installation
- unzip `customFlags` folder from release page
- move `customFlags` folder to your modules directory
- run `php bin/console prestashop:module install customFlags` in prestashop direcotry

## Configuration and usage
- First you have to add some custom flags
- go to `Modules / Module Manager` and find `Custom flags` module
- go to module config page
- specify required values
  - **Flag name** to differentiate flags from each other
  - **Text displayed on the flag** text visible on product flag
  - **Flag type** specifies way which flag is displayed
  - **optionally you can set the `trigger`**
    - choose trigger type which implies value which triggers flag
    - choose operator `<` or `>`
    - finally specify value which triggers flag
- now you can go to your `catalog / product`
- select product
- go to options page
- select flag
