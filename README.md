# Oro Test

## Setup and User Guide

This repository provides a Symfony-based application with command chaining functionality. Follow the steps below to set up and test the project.

### 1. Clone the Project
Clone the repository from GitHub:
```sh
git clone git@github.com:yvechir/oro_test.git
```

### 2. Navigate to the Project Directory
Move into the project folder:
```sh
cd oro_test/
```

### 3. Install Dependencies
Run the following command to install all necessary packages:
```sh
composer install
```

### 4. Check the Functionality

#### 4.1 Available Commands
Execute the following commands to see the output:
```sh
php bin/console foo:hello
php bin/console bar:hi
```

#### 4.2 Check Logs
To review the logs generated by the command chain execution, run:
```sh
cat var/log/command_chain.log
```

#### 4.3 Run Tests
To ensure everything is working correctly, execute the test suite:
```sh
php bin/phpunit
```


