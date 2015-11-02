# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.box = "ubuntu/trusty64"
  config.vm.hostname = "zray"
  config.vm.network :private_network, ip: "192.168.201.2"
  config.vm.provider :virtualbox do |v|
    v.name = "zray"
  end
  config.vm.provision "ansible" do |ansible|
    ansible.playbook = "zray_test.yml"
    ansible.inventory_path = "zray_test_inventory"
    ansible.limit = 'all'
    ansible.sudo = true
    ansible.host_key_checking = false
  end
end
