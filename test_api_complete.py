import requests
import json
import time

# Test API endpoints
base_url = "http://localhost:8000/api"

def test_login():
    print("Testing login endpoint...")
    data = {
        "email": "admin@example.com",
        "password": "password123"
    }
    
    try:
        response = requests.post(f"{base_url}/auth/login", 
                               json=data, 
                               headers={"Content-Type": "application/json"})
        
        if response.status_code == 200:
            result = response.json()
            print(f"✅ Login successful: {result.get('message', 'No message')}")
            print(f"   Token: {result.get('token', 'No token')}")
            print(f"   User: {result.get('user', {}).get('name', 'Unknown')}")
            return result.get('token')
        else:
            print(f"❌ Login failed: {response.status_code}")
            return None
            
    except Exception as e:
        print(f"❌ Login error: {e}")
        return None

def test_farms():
    print("\nTesting farms endpoint...")
    token = test_login()
    if not token:
        return False
        
    headers = {"Authorization": f"Bearer {token}"}
    
    try:
        response = requests.get(f"{base_url}/data/farms", headers=headers)
        
        if response.status_code == 200:
            farms = response.json()
            print(f"✅ Farms retrieved: {len(farms)} farms")
            for farm in farms:
                print(f"   - {farm.get('location', 'Unknown')} ({farm.get('crop_type', 'Unknown')})")
            return True
        else:
            print(f"❌ Farms failed: {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ Farms error: {e}")
        return False

def test_sensors():
    print("\nTesting sensors endpoint...")
    token = test_login()
    if not token:
        return False
        
    headers = {"Authorization": f"Bearer {token}"}
    
    try:
        response = requests.get(f"{base_url}/data/farms/550e8400-e29b-41d4-a716-446655440002/sensors?hours=24", headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            latest = data.get('latest', {})
            print(f"✅ Sensor data retrieved:")
            print(f"   Moisture: {latest.get('soilMoisture', 'N/A')}%")
            print(f"   Temperature: {latest.get('temperature', 'N/A')}°C")
            print(f"   Humidity: {latest.get('humidity', 'N/A')}%")
            return True
        else:
            print(f"❌ Sensors failed: {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ Sensors error: {e}")
        return False

def test_auto_mode():
    print("\nTesting auto mode endpoint...")
    token = test_login()
    if not token:
        return False
        
    headers = {"Authorization": f"Bearer {token}"}
    
    try:
        response = requests.get(f"{base_url}/irrigation/farms/550e8400-e29b-41d4-a716-446655440002/auto-mode", headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            print(f"✅ Auto mode status: {data.get('enabled', 'Unknown')}")
            return True
        else:
            print(f"❌ Auto mode failed: {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ Auto mode error: {e}")
        return False

def test_irrigation():
    print("\nTesting irrigation endpoint...")
    token = test_login()
    if not token:
        return False
        
    headers = {"Authorization": f"Bearer {token}"}
    
    try:
        response = requests.post(f"{base_url}/irrigation/farms/550e8400-e29b-41d4-a716-446655440002/irrigate", 
                               json={"manual": True, "duration": 12, "estimatedLitres": 420},
                               headers=headers)
        
        if response.status_code == 200:
            data = response.json()
            print(f"✅ Irrigation triggered: {data.get('message', 'No message')}")
            return True
        else:
            print(f"❌ Irrigation failed: {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ Irrigation error: {e}")
        return False

if __name__ == "__main__":
    print("=" * 50)
    print("AquaSense API Integration Test")
    print("=" * 50)
    
    # Run all tests
    login_success = test_login()
    farms_success = test_farms()
    sensors_success = test_sensors()
    auto_success = test_auto_mode()
    irrigation_success = test_irrigation()
    
    print("\n" + "=" * 50)
    print("TEST SUMMARY")
    print("=" * 50)
    
    tests = [
        ("Login", login_success),
        ("Farms", farms_success),
        ("Sensors", sensors_success),
        ("Auto Mode", auto_success),
        ("Irrigation", irrigation_success)
    ]
    
    passed = sum(1 for test, result in tests if result else 0)
    
    for test_name, result in tests:
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{test_name}: {status}")
    
    print(f"\nOverall: {passed}/{len(tests)} tests passed")
    
    if passed == len(tests):
        print("🎉 All API endpoints are working correctly!")
    else:
        print("⚠️  Some tests failed - check server logs")
