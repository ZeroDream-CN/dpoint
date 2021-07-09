local DriftPoint = 0
local LastPoint = 0
local LastSend = 0
local HideHud = true
local Passive = false
local CurrentHealth = 0

Citizen.CreateThread(function()
	while not NetworkIsPlayerActive(PlayerId()) do
		Citizen.Wait(0)
	end
	SendNUIMessage({
		msgtype = "setid",
		data = GetPlayerServerId(PlayerId())
	})
	SendNUIMessage({
		msgtype = "setname",
		data = GetPlayerName(PlayerId())
	})
end)

Citizen.CreateThread(function()
    while true do
		Citizen.Wait(0)
        ped = GetPlayerPed(-1)
        car = GetVehiclePedIsUsing(ped)
		selfPos = GetEntityCoords(ped)
        ang, speed = angle(car)
		CurrentHealth = GetVehicleBodyHealth(car)
        if IsPedInAnyVehicle(GetPlayerPed(-1), false) and IsThisACar(car) then
			if speed >= 3.0 and ang ~= 0 then
				DriftPoint = DriftPoint + ang
			end
		end
    end
end)

Citizen.CreateThread(function()
	while not NetworkIsPlayerActive(PlayerId()) do
		Citizen.Wait(0)
	end
	Citizen.Wait(5000)
	while true do
		Citizen.Wait(0)
		if GetGameTimer() - LastSend > 3000 and not HideHud then
			SendNUIMessage({
				msgtype = "hudstatus",
				data = false
			})
			DriftPoint = 0
			LastPoint = 0
			HideHud = true
		end
		if LastPoint ~= DriftPoint then
			SendNUIMessage({
				msgtype = "point",
				data = DriftPoint * 1.5,
				health = CurrentHealth,
			})
			LastPoint = DriftPoint
			LastSend = GetGameTimer()
			HideHud = false
		end
	end
end)

function IsThisACar(entity)
	return IsThisModelACar(GetEntityModel(entity))
end

function angle(veh)
    if not veh then
		return false
	end
    local vx, vy, vz = table.unpack(GetEntityVelocity(veh))
    local modV       = math.sqrt(vx * vx + vy * vy)
    local rx, ry, rz = table.unpack(GetEntityRotation(veh,0))
    local sn, cs     = -math.sin(math.rad(rz)), math.cos(math.rad(rz))
    if GetEntitySpeed(veh) * 3.6 < 5 or GetVehicleCurrentGear(veh) == 0 then 
		return 0, modV
	end
    local cosX = (sn * vx + cs * vy) / modV
	if cosX > 0.966 or cosX < 0 then
		return 0, modV
	end
	return math.deg(math.acos(cosX)) * 0.5, modV
end

function Notify(text)
    SetNotificationTextEntry('STRING')
    AddTextComponentString(text)
    DrawNotification(true, false)
end
