@{
    IncludeRules = @('PSUseCompatibleSyntax', 'PSUseCompatibleCommands', 'PSUseCompatibleTypes')
    Rules = @{
        PSUseCompatibleSyntax = @{
            Enable = $true
            TargetVersions = @('4.0')
        }
        PSUseCompatibleCommands = @{
            Enable = $true
            TargetProfiles = @('win-8_x64_6.3.9600.0_4.0_x64_4.0.30319.42000_framework')
        }
        PSUseCompatibleTypes = @{
            Enable = $true
            TargetProfiles = @('win-8_x64_6.3.9600.0_4.0_x64_4.0.30319.42000_framework')
        }
    }
}
